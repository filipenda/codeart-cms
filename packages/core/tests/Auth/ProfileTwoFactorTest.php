<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Confirmation\ConfirmationTokens;
use Codeart\Core\Confirmation\RequireConfirmation;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Tests\Support\AdminTestUser;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'optional');
});

function tfOn(array $extra = []): array
{
    return $extra + ['two_factor_secret' => encrypt('SECRETSECRET'), 'two_factor_recovery_codes' => encrypt(json_encode(['aaaa-1111', 'bbbb-2222'])), 'two_factor_confirmed_at' => now()];
}

/** A dangerous request the way the browser sends it: confirmation token for user + method + path, and a fresh password. */
function tfDanger($test, AdminTestUser $actor, string $method, string $path, bool $token = true)
{
    $data = $token ? ['confirmation' => app(ConfirmationTokens::class)->issue($actor, RequireConfirmation::scope($method, $path))] : [];

    return $test->withSession(['auth.password_confirmed_at' => time()])->actingAs($actor, 'admin')->call($method, $path, $data + ['_token' => csrf_token()]);
}

it('shows the three-step guide and a start button while 2FA is off', function () {
    $xp = dom($this->actingAs(staff('editor'), 'admin')->get('/admin/profile/two-factor')->assertOk()->getContent());
    expect(attr($xp, '//*[@data-two-factor]', 'data-two-factor-state'))->toBe('off')
        ->and(count_nodes($xp, '//ol[@data-two-factor-guide]/li'))->toBe(3)
        ->and(count_nodes($xp, '//form[contains(@action,"/user/two-factor-authentication")]//button'))->toBe(1)
        ->and(count_nodes($xp, '//*[@data-two-factor-qr]'))->toBe(0);
});

it('shows the QR (dark on white), the manual key with a copy button, the code field and "Aktiviraj 2FA" once the secret exists', function () {
    $user = staff('editor');
    $this->actingAs($user, 'admin')->withSession(['auth.password_confirmed_at' => time()])->post('/admin/user/two-factor-authentication')->assertRedirect();
    $html = $this->actingAs($user->fresh(), 'admin')->get('/admin/profile/two-factor')->assertOk()->getContent();
    $xp = dom($html);
    $secret = decrypt($user->fresh()->two_factor_secret);

    expect(attr($xp, '//*[@data-two-factor]', 'data-two-factor-state'))->toBe('pending')
        ->and(count_nodes($xp, '//*[@data-two-factor-qr]//svg'))->toBe(1)
        ->and($html)->toContain('var(--color-qr-bg)')->toContain('var(--color-qr-fg)')->toContain('bg-qr-bg')
        ->and(text_of($xp, '//*[@data-two-factor-secret]'))->toBe($secret)
        ->and(count_nodes($xp, '//*[@data-copy-button]'))->toBe(1)
        ->and(count_nodes($xp, '//form[@data-two-factor-confirm]//input[@name="code"]'))->toBe(1)
        ->and(text_of($xp, '//form[@data-two-factor-confirm]//button'))->toBe('Aktiviraj 2FA');
});

it('activates 2FA with a valid code, shows the recovery codes once and logs it', function () {
    $user = staff('editor');
    $this->actingAs($user, 'admin')->withSession(['auth.password_confirmed_at' => time()])->post('/admin/user/two-factor-authentication');
    $this->actingAs($user->fresh(), 'admin')->from('/admin/profile/two-factor')->post('/admin/user/confirmed-two-factor-authentication', ['code' => '000000'])->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', 'code');
    $this->actingAs($user->fresh(), 'admin')->from('/admin/profile/two-factor')->post('/admin/user/confirmed-two-factor-authentication', ['code' => totp($user)])->assertRedirect('/admin/profile/two-factor');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and(Activity::query()->where('event', 'two_factor.enabled')->exists())->toBeTrue();
    $xp = dom($this->actingAs($user->fresh(), 'admin')->withSession(['status' => 'two-factor-authentication-confirmed'])->get('/admin/profile/two-factor')->getContent());
    expect(count_nodes($xp, '//*[@data-recovery-codes]//li'))->toBe(8);
    $again = dom($this->actingAs($user->fresh(), 'admin')->get('/admin/profile/two-factor')->getContent());
    expect(count_nodes($again, '//*[@data-recovery-codes]'))->toBe(0); // not shown again without a password confirmation
});

it('shows a green status, hides the codes until the password is confirmed, then shows them', function () {
    $user = staff('editor', tfOn());
    $xp = dom($this->actingAs($user, 'admin')->get('/admin/profile/two-factor')->getContent());
    expect(attr($xp, '//*[@data-two-factor]', 'data-two-factor-state'))->toBe('on')
        ->and(attr($xp, '//*[@data-two-factor-status]', 'class'))->toContain('bg-success-bg')->toContain('text-success-text')
        ->and(text_of($xp, '//*[@data-two-factor-status]'))->toContain('2FA je aktivna')
        ->and(count_nodes($xp, '//*[@data-recovery-codes]'))->toBe(0)
        ->and(count_nodes($xp, '//a[@data-show-codes]'))->toBe(1);

    $this->actingAs($user, 'admin')->get('/admin/profile/two-factor?codes=1')->assertRedirect(route('password.confirm'));
    $xp = dom($this->withSession(['auth.password_confirmed_at' => time()])->actingAs($user, 'admin')->get('/admin/profile/two-factor?codes=1')->assertOk()->getContent());
    expect(array_map(fn ($n) => trim($n->textContent), iterator_to_array($xp->query('//*[@data-recovery-codes]//li'))))->toBe(['aaaa-1111', 'bbbb-2222'])
        ->and(count_nodes($xp, '//*[@data-recovery-codes]//button'))->toBeGreaterThanOrEqual(2); // copy + download (Alpine)
});

it('makes new recovery codes only with the server confirmation and a fresh password, and logs it', function () {
    $user = staff('editor', tfOn());
    $old = $user->two_factor_recovery_codes;
    $path = '/admin/profile/two-factor/recovery-codes';

    $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($user, 'admin')->post($path)->assertRedirect(); // no token: nothing happens
    expect($user->fresh()->two_factor_recovery_codes)->toBe($old);

    $this->flushSession();
    tfDanger($this, $user, 'POST', $path, false); // still no token
    expect($user->fresh()->two_factor_recovery_codes)->toBe($old);

    tfDanger($this, $user, 'POST', $path)->assertRedirect(route('admin.profile.two-factor'));
    expect($user->fresh()->two_factor_recovery_codes)->not->toBe($old)
        ->and(Activity::query()->where('event', 'two_factor.recovery_codes_regenerated')->count())->toBe(1);
});

it('asks for the password before new recovery codes even with a token', function () {
    $user = staff('editor', tfOn());
    $path = '/admin/profile/two-factor/recovery-codes';
    $token = app(ConfirmationTokens::class)->issue($user, RequireConfirmation::scope('POST', $path));
    $this->actingAs($user, 'admin')->post($path, ['confirmation' => $token])->assertRedirect(route('password.confirm'));
    expect($user->fresh()->two_factor_recovery_codes)->toBe($user->two_factor_recovery_codes);
});

it('turns 2FA off only with confirmation and fresh password, where the role does not require it', function () {
    $user = staff('editor', tfOn());
    $path = '/admin/profile/two-factor';
    tfDanger($this, $user, 'DELETE', $path, false);
    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    tfDanger($this, $user, 'DELETE', $path)->assertRedirect(route('admin.profile.two-factor'));
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and(Activity::query()->where('event', 'two_factor.disabled')->count())->toBe(1);
});

it('has no disable button and refuses a disable request when 2FA is required for the role', function () {
    $settings = app(Settings::class);
    $settings->set('security.2fa.mode', 'required');
    $settings->set('security.2fa.required_roles', ['admin']);
    $admin = staff('admin', tfOn());

    $xp = dom($this->actingAs($admin, 'admin')->get('/admin/profile/two-factor')->getContent());
    expect(count_nodes($xp, '//*[@data-disable]'))->toBe(0)->and(count_nodes($xp, '//*[@data-required-note]'))->toBe(1);

    tfDanger($this, $admin, 'DELETE', '/admin/profile/two-factor')->assertForbidden();
    $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($admin, 'admin')->deleteJson('/admin/user/two-factor-authentication')->assertForbidden(); // Fortify's own route too
    expect($admin->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and(Activity::query()->where('event', 'two_factor.disabled')->count())->toBe(0);

    $editor = staff('editor', tfOn()); // a role that is not required may still turn it off
    $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($editor, 'admin')->deleteJson('/admin/user/two-factor-authentication')->assertOk();
});

it('has the 2FA texts in sr, en and sr-Cyrl', function () {
    foreach (['tf_title', 'tf_step1_title', 'tf_activate', 'tf_active', 'tf_regen', 'tf_disable'] as $key) {
        foreach (['sr', 'en', 'sr-Cyrl'] as $locale) {
            expect(__('codeart::profile.'.$key, [], $locale))->not->toBe('codeart::profile.'.$key);
        }
    }
    expect(__('codeart::profile.tf_active', [], 'sr'))->toBe('2FA je aktivna');
});
