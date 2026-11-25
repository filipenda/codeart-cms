<?php

use Codeart\Core\Settings\Settings;
use Codeart\Core\Tests\Support\AdminTestUser;
use Spatie\Activitylog\Models\Activity;

/** Walk through setup as the real screens will: enable, then confirm with a code. */
function enableTwoFactor(AdminTestUser $user): AdminTestUser
{
    test()->actingAs($user, 'admin')->withSession(['auth.password_confirmed_at' => time()]);
    test()->postJson('/admin/user/two-factor-authentication')->assertOk();
    test()->postJson('/admin/user/confirmed-two-factor-authentication', ['code' => totp($user)])->assertOk();

    return $user->fresh();
}

it('defaults to required for super-admin and admin', function () {
    $settings = app(Settings::class);

    expect($settings->get('security.2fa.mode'))->toBe('required')
        ->and($settings->get('security.2fa.required_roles'))->toBe(['super-admin', 'admin']);
});

it('sets up two-factor: enable, confirm, recovery codes', function () {
    $user = staff('editor');
    $this->actingAs($user, 'admin')->withSession(['auth.password_confirmed_at' => time()]);

    $this->postJson('/admin/user/two-factor-authentication')->assertOk();
    $this->getJson('/admin/user/two-factor-secret-key')->assertOk()->assertJsonStructure(['secretKey']);
    $this->getJson('/admin/user/two-factor-qr-code')->assertOk()->assertJsonStructure(['svg', 'url']);
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse(); // not until confirmed

    $this->postJson('/admin/user/confirmed-two-factor-authentication', ['code' => '000000'])->assertStatus(422);
    $this->postJson('/admin/user/confirmed-two-factor-authentication', ['code' => totp($user)])->assertOk();
    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    $codes = $this->getJson('/admin/user/two-factor-recovery-codes')->assertOk()->json();
    expect($codes)->toHaveCount(8);
    $this->postJson('/admin/user/two-factor-recovery-codes')->assertOk();
    expect($this->getJson('/admin/user/two-factor-recovery-codes')->json())->not->toEqual($codes);

    $this->deleteJson('/admin/user/two-factor-authentication')->assertOk();
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('asks for the second factor after the password, and accepts a code', function () {
    $user = enableTwoFactor(staff('editor'));
    $this->flushSession();
    app('auth')->forgetGuards();

    $this->postJson('/admin/login', ['email' => $user->email, 'password' => 'Secret-pass-123'])->assertOk()->assertJson(['two_factor' => true]);
    expect(auth('admin')->check())->toBeFalse();
    carrySession();

    $this->postJson('/admin/two-factor-challenge', ['code' => '000000'])->assertStatus(422);
    carrySession();
    $this->postJson('/admin/two-factor-challenge', ['code' => totp($user, 1)])->assertNoContent();
    expect(auth('admin')->check())->toBeTrue();
});

it('accepts a recovery code once and replaces it', function () {
    $user = enableTwoFactor(staff('editor'));
    $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);
    $this->flushSession();
    app('auth')->forgetGuards();

    $this->postJson('/admin/login', ['email' => $user->email, 'password' => 'Secret-pass-123'])->assertJson(['two_factor' => true]);
    carrySession();
    $this->postJson('/admin/two-factor-challenge', ['recovery_code' => $codes[0]])->assertNoContent();

    $after = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);
    expect($after)->not->toContain($codes[0])->toHaveCount(8);
    expect(Activity::query()->where('event', 'two_factor.recovery_code_used')->exists())->toBeTrue();
});

it('requires two-factor for the configured roles until it is set up', function (string $role, bool $mustSetUp) {
    $user = staff($role);
    $this->actingAs($user, 'admin');

    if ($mustSetUp) {
        $this->getJson('/admin/access')->assertForbidden()->assertJson(['reason' => 'two_factor_required']);
    } else {
        $this->getJson('/admin/access')->assertNoContent();
    }
})->with([
    'super-admin' => ['super-admin', true],
    'admin' => ['admin', true],
    'editor' => ['editor', false],
]);

it('lets only the setup routes, logout and the session state through while two-factor is pending', function () {
    $user = staff('admin');
    $this->actingAs($user, 'admin')->withSession(['auth.password_confirmed_at' => time()]);

    $this->getJson('/admin/access')->assertForbidden();
    $this->getJson('/admin/session')->assertOk()->assertJsonPath('two_factor.must_set_up', true)->assertJsonPath('can_access_admin', false);
    $this->postJson('/admin/user/two-factor-authentication')->assertOk();
    $this->postJson('/admin/logout')->assertNoContent();
});

it('lets the user in once two-factor is set up', function () {
    $user = enableTwoFactor(staff('admin'));

    $this->getJson('/admin/access')->assertNoContent();
});

it('follows the mode setting', function (string $mode, string $role, bool $required) {
    app(Settings::class)->set('security.2fa.mode', $mode);
    app(Settings::class)->set('security.2fa.required_roles', ['editor']);

    expect(staff($role)->mustSetUpTwoFactor())->toBe($required);
})->with([
    'off' => ['off', 'editor', false],
    'optional' => ['optional', 'editor', false],
    'required, listed role' => ['required', 'editor', true],
    'required, other role' => ['required', 'admin', false],
]);

it('refuses to start two-factor setup when the mode is off', function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    $this->actingAs(staff('editor'), 'admin')->withSession(['auth.password_confirmed_at' => time()]);

    $this->postJson('/admin/user/two-factor-authentication')->assertForbidden();
});

it('asks for the password again before sensitive two-factor actions', function () {
    $this->actingAs(staff('editor'), 'admin');

    $this->postJson('/admin/user/two-factor-authentication')->assertStatus(423);
});

it('resets two-factor from the command line and logs it', function () {
    $user = enableTwoFactor(staff('editor'));

    $this->artisan('user:reset-2fa', ['email' => $user->email])->assertSuccessful();

    $user = $user->fresh();
    expect($user->two_factor_secret)->toBeNull()->and($user->two_factor_recovery_codes)->toBeNull()->and($user->two_factor_confirmed_at)->toBeNull();
    $entry = Activity::query()->where('event', 'two_factor.reset')->first();
    expect($entry)->not->toBeNull()->and($entry->subject_id)->toBe($user->id)->and($entry->properties['via'])->toBe('command line');
    $this->artisan('user:reset-2fa', ['email' => 'nobody@example.com'])->assertFailed();
});
