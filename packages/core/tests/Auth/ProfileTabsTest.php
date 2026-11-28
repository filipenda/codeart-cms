<?php

require_once __DIR__.'/../Support/dom.php';

use Carbon\CarbonImmutable;
use Codeart\Core\Profile\PersonName;
use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Support\DisplayTime;
use Codeart\Core\Tables\Column;
use Codeart\Core\Tests\Support\TableItem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'optional');
});

// ---- Stage 2: components ----------------------------------------------------------------------------------------------

it('draws a leading icon in the select and keeps its states', function () {
    view()->share('errors', new ViewErrorBag);
    $html = Blade::render('<x-codeart::select name="zone" icon="clock" label="Zone" hint="A hint" :options="[\'a\' => \'A\']" disabled />');
    $xp = dom($html);
    expect(count_nodes($xp, '//*[@data-select-icon]//svg'))->toBe(1)
        ->and(attr($xp, '//select', 'class'))->toContain('ps-12')->toContain('disabled:opacity-60')
        ->and(attr($xp, '//select', 'disabled'))->not->toBeNull()
        ->and(attr($xp, '//select', 'aria-describedby'))->toBe('field-zone-hint');
    $plain = Blade::render('<x-codeart::select name="zone" label="Zone" :options="[\'a\' => \'A\']" />');
    expect($plain)->not->toContain('data-select-icon')->toContain('ps-4');
    $err = Blade::render('<x-codeart::select name="zone" icon="clock" label="Zone" :bag="$bag" :options="[]" />', ['bag' => (new MessageBag)->add('zone', 'Bad')]);
    expect($err)->toContain('aria-invalid="true"')->toContain('Bad');
});

it('lists the configured password rules and follows setting changes', function () {
    $settings = app(Settings::class);
    $render = fn (): string => $this->actingAs(staff('editor'), 'admin')->get('/admin/profile/password')->assertOk()->getContent();

    $settings->set('security.password.min_length', 12);
    $settings->set('security.password.require_mixed_case', true);
    $settings->set('security.password.require_number', true);
    $settings->set('security.password.require_symbol', false);
    $settings->set('security.password.check_breached', true);
    $xp = dom($render());
    $rules = array_map(fn ($n) => $n->getAttribute('data-rule'), iterator_to_array($xp->query('//ul[@data-requirements]/li')));
    expect($rules)->toBe(['min_length', 'mixed_case', 'number'])
        ->and(text_of($xp, '//li[@data-rule="min_length"]'))->toContain('12')
        ->and(count_nodes($xp, '//*[@data-breached-hint]'))->toBe(1);

    $settings->set('security.password.min_length', 8);
    $settings->set('security.password.require_mixed_case', false);
    $settings->set('security.password.require_symbol', true);
    $settings->set('security.password.check_breached', false);
    $xp = dom($render());
    $rules = array_map(fn ($n) => $n->getAttribute('data-rule'), iterator_to_array($xp->query('//ul[@data-requirements]/li')));
    expect($rules)->toBe(['min_length', 'number', 'symbol'])->and(text_of($xp, '//li[@data-rule="min_length"]'))->toContain('8')
        ->and(count_nodes($xp, '//*[@data-breached-hint]'))->toBe(0);
});

it('renders the strength meter with five segments, a label and a static list without JS', function () {
    $html = $this->actingAs(staff('editor'), 'admin')->get('/admin/profile/password')->getContent();
    $xp = dom($html);
    expect(count_nodes($xp, '//*[@data-password-strength]//*[@data-segment]'))->toBe(5)
        ->and(count_nodes($xp, '//*[@data-strength-label]'))->toBe(1)
        ->and(count_nodes($xp, '//ul[@data-requirements]/li'))->toBeGreaterThanOrEqual(1)
        ->and($html)->toContain('codeartPasswordStrength')->toContain('Slaba lozinka')->toContain('Jaka lozinka')
        ->and($html)->not->toMatch('/data-segment[^>]*class="[^"]*#[0-9a-f]{3,6}/i');
    // the meter itself needs Alpine; the list stays visible without it (no `hidden` on the list)
    expect(attr($xp, '//*[@data-strength-meter]', 'hidden'))->not->toBeNull()
        ->and(attr($xp, '//ul[@data-requirements]', 'hidden'))->toBeNull();
});

it('has a copy button that needs JS and keeps the value visible elsewhere', function () {
    $html = Blade::render('<x-codeart::copy-button value="ABC123SECRET" />');
    expect($html)->toContain('codeartCopy')->toContain('data-copy-button')->toContain(__('codeart::ui.copy'))->toContain(__('codeart::ui.copied'))
        ->and(dom($html)->query('//*[@data-copy-button][@hidden]')->length)->toBe(1);
});

// ---- Stage 3: time zone -----------------------------------------------------------------------------------------------

it('converts stored UTC dates for Belgrade, UTC and New York', function () {
    $time = app(DisplayTime::class);
    $utc = '2026-01-15 12:00:00';
    foreach (['Europe/Belgrade' => '13:00', 'UTC' => '12:00', 'America/New_York' => '07:00'] as $zone => $hm) {
        $time->use($zone);
        expect($time->format($utc, 'H:i'))->toBe($hm)->and($time->zone())->toBe($zone);
    }
    $time->use('Europe/Belgrade');
    expect($time->format('2026-07-15 12:00:00', 'H:i'))->toBe('14:00') // summer time
        ->and($time->format(null))->toBe('—')->and($time->format('not a date'))->toBe('—');
});

it('handles the daylight saving boundary', function () {
    $time = app(DisplayTime::class);
    $time->use('America/New_York');
    expect($time->convert('2026-03-08 06:59:00')->format('H:i P'))->toBe('01:59 -05:00')
        ->and($time->convert('2026-03-08 07:00:00')->format('H:i P'))->toBe('03:00 -04:00');
    $time->use('Europe/Belgrade');
    expect($time->convert('2026-03-29 00:59:00')->format('H:i P'))->toBe('01:59 +01:00')
        ->and($time->convert('2026-03-29 01:00:00')->format('H:i P'))->toBe('03:00 +02:00');
});

it('falls back to the site zone and refuses invalid zones', function () {
    $time = app(DisplayTime::class);
    $time->use(null);
    expect($time->zone())->toBe('Europe/Belgrade');
    $time->use('Mars/Olympus');
    expect($time->zone())->toBe('Europe/Belgrade');
    app(Settings::class)->set('site.timezone', 'Asia/Tokyo');
    $time->use(null);
    expect($time->zone())->toBe('Asia/Tokyo');
    expect(fn () => app(Settings::class)->set('site.timezone', 'Nowhere/Land'))->toThrow(InvalidSettingValue::class);
    expect(DisplayTime::isValid('Europe/Belgrade'))->toBeTrue()->and(DisplayTime::isValid('Mars/Olympus'))->toBeFalse()->and(DisplayTime::isValid(''))->toBeFalse();
});

it('lists zones by region with the current offset in the label', function () {
    app()->setLocale('sr');
    $winter = new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC'));
    $summer = new DateTimeImmutable('2026-07-15 12:00:00', new DateTimeZone('UTC'));
    $options = DisplayTime::options($winter);
    expect($options)->toHaveKeys(['Europe', 'America', 'Asia', 'UTC'])
        ->and($options['Europe']['Europe/Belgrade'])->toBe('(UTC+01:00) Beograd')
        ->and(DisplayTime::options($summer)['Europe']['Europe/Belgrade'])->toBe('(UTC+02:00) Beograd')
        ->and($options['America']['America/New_York'])->toBe('(UTC-05:00) Njujork')
        ->and($options['UTC']['UTC'])->toBe('(UTC+00:00) UTC');
    app()->setLocale('en');
    expect(DisplayTime::label('Europe/Belgrade', $winter))->toBe('(UTC+01:00) Belgrade');
});

it('shows dates in the signed-in user zone and in the site zone otherwise', function () {
    $when = CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC');
    $ny = staff('super-admin', ['timezone' => 'America/New_York', 'last_login_at' => $when]);
    $xp = dom($this->actingAs($ny, 'admin')->get('/admin/profile')->assertOk()->getContent());
    expect(attr($xp, '//*[@data-last-sign-in]', 'title'))->toBe('15.01.2026. 07:00');

    $site = staff('super-admin', ['timezone' => null, 'last_login_at' => $when]);
    $xp = dom($this->actingAs($site, 'admin')->get('/admin/profile')->getContent());
    expect(attr($xp, '//*[@data-last-sign-in]', 'title'))->toBe('15.01.2026. 13:00');

    $edit = $this->actingAs($ny, 'admin')->get('/admin/users/'.$site->id)->assertOk()->getContent();
    expect($edit)->toContain('15.01.2026. 07:00');
});

it('shows table date cells in the display zone', function () {
    $user = staff('super-admin', ['timezone' => 'America/New_York']);
    app(DisplayTime::class)->use('America/New_York');
    $col = Column::date('created_at', 'Created');
    $html = Blade::render('<x-codeart::table.cell :column="$c" :row="$r" />', ['c' => $col, 'r' => new TableItem(['created_at' => '2026-01-15 12:00:00'])]);
    expect($html)->toContain('2026-01-15T07:00:00-05:00');
});

// ---- Stage 4: personal details ----------------------------------------------------------------------------------------

it('composes and splits the name', function () {
    expect(PersonName::compose(' Ana ', ' Marija  Petrović '))->toBe('Ana Marija Petrović')
        ->and(PersonName::compose('Ana', ''))->toBe('Ana')
        ->and(PersonName::split('Ana Marija Petrović'))->toBe(['Ana', 'Marija Petrović'])
        ->and(PersonName::split('Madonna'))->toBe(['Madonna', ''])
        ->and(PersonName::split('  '))->toBe(['', '']);
});

it('pre-fills first and last name from `name` without rewriting it', function () {
    $me = staff('editor', ['name' => 'Ana Marija Petrović']);
    $xp = dom($this->actingAs($me, 'admin')->get('/admin/profile')->getContent());
    expect(attr($xp, '//input[@name="first_name"]', 'value'))->toBe('Ana')
        ->and(attr($xp, '//input[@name="last_name"]', 'value'))->toBe('Marija Petrović');
    $fresh = $me->fresh();
    expect($fresh->first_name)->toBeNull()->and($fresh->last_name)->toBeNull()->and($fresh->name)->toBe('Ana Marija Petrović');
});

it('renders the personal fields with icons and the card buttons', function () {
    $xp = dom($this->actingAs(staff('editor'), 'admin')->get('/admin/profile')->getContent());
    foreach (['first_name', 'last_name', 'phone', 'timezone', 'locale', 'theme_preference'] as $name) {
        expect(count_nodes($xp, '//*[@data-profile-form]//*[@data-field][.//*[@name="'.$name.'"]]//svg'))->toBeGreaterThanOrEqual(1, $name);
    }
    expect(count_nodes($xp, '//input[@data-profile-email][@readonly]'))->toBe(1)
        ->and(count_nodes($xp, '//input[@name="email"]'))->toBe(0)
        ->and(count_nodes($xp, '//optgroup'))->toBeGreaterThan(3)
        ->and(count_nodes($xp, '//form[@data-profile-form]//footer[@data-card-footer]//button[@type="submit"]'))->toBe(1)
        ->and(count_nodes($xp, '//form[@data-profile-form]//footer//a'))->toBe(1);
});

it('saves first/last name (composing `name`), phone, zone, language and theme, but never the e-mail', function () {
    $me = staff('editor', ['name' => 'Old Name', 'email' => 'me@example.com']);
    $this->actingAs($me, 'admin')->put('/admin/profile', [
        'first_name' => ' Ana ', 'last_name' => 'Petrović', 'phone' => '+381 11 123', 'timezone' => 'America/New_York',
        'locale' => 'en', 'theme_preference' => 'dark', 'email' => 'evil@example.com',
    ])->assertRedirect('/admin/profile')->assertSessionHasNoErrors();
    $fresh = $me->fresh();
    expect($fresh->name)->toBe('Ana Petrović')->and($fresh->first_name)->toBe('Ana')->and($fresh->last_name)->toBe('Petrović')
        ->and($fresh->phone)->toBe('+381 11 123')->and($fresh->timezone)->toBe('America/New_York')
        ->and($fresh->locale)->toBe('en')->and($fresh->theme_preference)->toBe('dark')->and($fresh->email)->toBe('me@example.com');
    $log = Activity::query()->where('event', 'profile.updated')->latest('id')->first();
    expect($log->properties['new']['timezone'])->toBe('America/New_York');

    $this->actingAs($me, 'admin')->put('/admin/profile', ['first_name' => 'Ana', 'last_name' => 'P', 'timezone' => ''])->assertSessionHasNoErrors();
    expect($me->fresh()->timezone)->toBeNull();
});

it('validates the zone and the required names', function () {
    $me = staff('editor');
    $this->actingAs($me, 'admin')->put('/admin/profile', ['first_name' => 'A', 'last_name' => 'B', 'timezone' => 'Mars/Olympus'])->assertSessionHasErrors('timezone');
    $this->actingAs($me, 'admin')->put('/admin/profile', ['first_name' => '', 'last_name' => 'B'])->assertSessionHasErrors('first_name');
    $this->actingAs($me, 'admin')->put('/admin/profile', ['first_name' => 'A', 'last_name' => 'B', 'phone' => 'abc'])->assertSessionHasErrors('phone');
    expect($me->fresh()->timezone)->toBeNull();
});

it('has the new profile texts in sr, en and sr-Cyrl', function () {
    foreach (['first_name', 'last_name', 'timezone', 'pw_weak', 'pw_medium', 'pw_strong', 'pw_requirements', 'pw_min'] as $key) {
        foreach (['sr', 'en', 'sr-Cyrl'] as $locale) {
            expect(__('codeart::profile.'.$key, ['n' => 10], $locale))->not->toBe('codeart::profile.'.$key);
        }
    }
    expect(__('codeart::profile.first_name', [], 'sr'))->toBe('Ime')->and(__('codeart::profile.last_name', [], 'sr'))->toBe('Prezime');
});
