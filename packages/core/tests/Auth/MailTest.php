<?php

use Codeart\Core\Auth\Mail\InvitationMail;
use Codeart\Core\Auth\Mail\NewRegistrationPendingMail;
use Codeart\Core\Auth\Mail\RegistrationApprovedMail;
use Codeart\Core\Auth\Mail\RegistrationReceivedMail;
use Codeart\Core\Auth\Mail\ResetPasswordMail;
use Codeart\Core\Auth\Mail\TestMail;
use Codeart\Core\Auth\Mail\VerifyEmailMail;
use Codeart\Core\Mail\AuthMailer;
use Codeart\Core\Mail\MailConfig;
use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Theme\Color;
use Codeart\Core\Theme\ThemeSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

it('applies stored mail settings to the mail config at runtime', function () {
    app(Settings::class)->setMany([
        'mail.from_name' => 'Acme CMS', 'mail.from_address' => 'cms@acme.rs', 'mail.driver' => 'smtp',
        'mail.host' => 'smtp.acme.rs', 'mail.port' => 465, 'mail.username' => 'cms', 'mail.password' => 'smtp-secret-pass', 'mail.encryption' => 'ssl',
    ]);
    $this->refreshApplication();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.from.address'))->toBe('cms@acme.rs')
        ->and(config('mail.from.name'))->toBe('Acme CMS')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.acme.rs')
        ->and(config('mail.mailers.smtp.port'))->toBe(465)
        ->and(config('mail.mailers.smtp.username'))->toBe('cms')
        ->and(config('mail.mailers.smtp.password'))->toBe('smtp-secret-pass')
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps');
});

it('keeps the app\'s own mail config when nothing is stored', function () {
    config(['mail.default' => 'array', 'mail.from.address' => 'env@example.com']);

    app(MailConfig::class)->apply();

    expect(config('mail.default'))->toBe('array')->and(config('mail.from.address'))->toBe('env@example.com')
        ->and(app(Settings::class)->get('mail.driver'))->toBe('log');
});

it('stores the SMTP password encrypted and only ever shows it masked', function () {
    $settings = app(Settings::class);
    $settings->set('mail.password', 'smtp-secret-pass');

    expect($settings->masked('mail.password'))->toBe('********')
        ->and(DB::table('settings')->where('key', 'mail.password')->value('value'))->not->toContain('smtp-secret-pass')
        ->and($settings->get('mail.password'))->toBe('smtp-secret-pass');
    $this->artisan('settings:get', ['key' => 'mail.password'])->expectsOutputToContain('********')->assertSuccessful();
    $this->artisan('settings:list', ['--group' => 'mail'])->doesntExpectOutputToContain('smtp-secret-pass')->assertSuccessful();
});

it('validates the mail settings', function () {
    $settings = app(Settings::class);

    expect(fn () => $settings->set('mail.driver', 'sendmail'))->toThrow(InvalidSettingValue::class)
        ->and(fn () => $settings->set('mail.encryption', 'starttls'))->toThrow(InvalidSettingValue::class)
        ->and(fn () => $settings->set('mail.from_address', 'not an address'))->toThrow(InvalidSettingValue::class)
        ->and(fn () => $settings->set('mail.port', 70000))->toThrow(InvalidSettingValue::class);
});

it('does not crash when the database is not ready', function () {
    Schema::dropAllTables();

    app(MailConfig::class)->apply();
    $this->refreshApplication(); // the whole boot, with no tables at all

    expect(config('session.lifetime'))->toBe(120)->and(config('mail.default'))->toBe('array');
});

it('sends a test message with mail:test', function () {
    Mail::fake();

    $this->artisan('mail:test', ['address' => 'me@example.com'])->assertSuccessful();

    Mail::assertSent(TestMail::class, fn ($m) => $m->hasTo('me@example.com'));
    $this->artisan('mail:test', ['address' => 'not-an-address'])->assertFailed();
});

it('reports a failed test message without leaking the password', function () {
    app(Settings::class)->setMany(['mail.driver' => 'smtp', 'mail.host' => '127.0.0.1', 'mail.port' => 1, 'mail.password' => 'smtp-secret-pass']);
    $this->refreshApplication();

    $this->artisan('mail:test', ['address' => 'me@example.com'])->expectsOutputToContain('Sending failed')->doesntExpectOutputToContain('smtp-secret-pass')->assertFailed();
});

dataset('locales', [
    'sr' => ['sr', 'Dobro došli'],
    'sr-Cyrl' => ['sr-Cyrl', 'Добро дошли'],
    'en' => ['en', 'Welcome to'],
]);

it('sends in the user\'s language', function (string $locale, string $phrase) {
    Mail::fake();
    $user = staff('editor', ['locale' => $locale]);

    app(AuthMailer::class)->send($user, new InvitationMail('Ana', 'https://example.com/invite', 7));

    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use ($locale, $phrase) {
        expect($mail->locale)->toBe($locale)->and($mail->render())->toContain($phrase)->not->toContain('codeart::');

        return true;
    });
})->with('locales');

it('falls back to the app language when the user has none or an unknown one', function (?string $locale) {
    Mail::fake();
    app()->setLocale('en');

    app(AuthMailer::class)->send(staff('editor', ['locale' => $locale]), new InvitationMail('Ana', 'https://example.com/invite', 7));

    Mail::assertSent(InvitationMail::class, fn ($m) => $m->locale === 'en' && str_contains($m->render(), 'Welcome to'));
})->with([null, 'xx']);

it('renders every system mail in every language, with no missing text', function (string $locale) {
    $user = staff('editor', ['locale' => $locale]);
    $mails = [
        new InvitationMail('Ana', 'https://example.com/i', 7),
        new ResetPasswordMail($user, 'token123'),
        new VerifyEmailMail($user),
        new RegistrationReceivedMail('Ana', true),
        new RegistrationApprovedMail('Ana'),
        new NewRegistrationPendingMail('Ana', 'ana@example.com'),
        new TestMail,
    ];

    foreach ($mails as $mail) {
        $html = $mail->locale($locale)->render();
        expect($html)->not->toContain('codeart::')->not->toContain('mail.')->and($mail->locale($locale)->envelope()->subject)->not->toContain('codeart::');
    }
})->with(['sr', 'sr-Cyrl', 'en']);

it('takes its colors from the theme, never from the template', function () {
    app(Settings::class)->set('theme.primary', '#336699');
    $html = (new InvitationMail('Ana', 'https://example.com/i', 7))->render();
    $palette = app(ThemeSettings::class)->emailPalette();

    expect($html)->toContain('#336699')
        ->and($html)->toContain('background:'.$palette['primary'])
        ->and($html)->toContain('color:'.$palette['on_primary']);

    app(Settings::class)->set('theme.primary', '#aa2233');
    expect((new InvitationMail('Ana', 'https://example.com/i', 7))->render())->toContain('#aa2233')->not->toContain('#336699');
});

it('puts the more readable of the two base colors on the brand color', function () {
    foreach (['#C9F31D', '#112233', '#ffffff', '#ff0000', '#336699'] as $primary) {
        app(Settings::class)->set('theme.primary', $primary);
        $p = app(ThemeSettings::class)->emailPalette();
        $other = $p['on_primary'] === $p['text'] ? config('codeart.theme_base.paper') : $p['text'];

        expect(Color::contrast($p['primary'], $p['on_primary']))
            ->toBeGreaterThanOrEqual(Color::contrast($p['primary'], $other));
    }
});
