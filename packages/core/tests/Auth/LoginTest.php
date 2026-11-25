<?php

use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\SettingsManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

function login(string $email, string $password = 'Secret-pass-123', array $extra = []): TestResponse
{
    return test()->postJson('/admin/login', ['email' => $email, 'password' => $password, ...$extra]);
}

it('signs in with e-mail and password', function () {
    $user = staff('editor');

    login($user->email)->assertOk();

    expect(auth('admin')->check())->toBeTrue()
        ->and($user->fresh()->last_login_at)->not->toBeNull()
        ->and($user->fresh()->last_login_ip)->not->toBeNull();
});

it('gives the same error for a wrong password, an unknown e-mail and an inactive account', function () {
    $user = staff('editor');
    $inactive = staff('editor', ['is_active' => false]);

    $responses = [
        login($user->email, 'wrong-password-1'),
        login('nobody@example.com'),
        login($inactive->email),
    ];

    foreach ($responses as $response) {
        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }
    expect($responses[0]->json())->toEqual($responses[1]->json())->toEqual($responses[2]->json())
        ->and($responses[0]->json('errors.email.0'))->toBe(__('codeart::framework.auth.failed'))
        ->and(auth('admin')->check())->toBeFalse();
});

it('locks out after the configured attempts and lets go after the decay', function () {
    app(Settings::class)->set('security.login.max_attempts', 2);
    app(Settings::class)->set('security.login.decay_minutes', 3);
    $user = staff('editor');

    login($user->email, 'wrong-password-1')->assertStatus(422);
    login($user->email, 'wrong-password-1')->assertStatus(422);
    $locked = login($user->email); // the right password does not help while locked out
    $locked->assertStatus(429);
    expect($locked->json('errors.email.0'))->toContain('seconds');

    // another address is not affected
    login(staff('editor')->email)->assertOk();
    app('auth')->forgetGuards();
    $this->flushSession();

    $this->travel(4)->minutes();
    login($user->email)->assertOk();
});

it('applies remember me for the configured days', function () {
    app(Settings::class)->set('security.login.remember_days', 7);
    $user = staff('editor');

    $response = login($user->email, extra: ['remember' => true])->assertOk();

    $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_admin'));
    expect($cookie)->not->toBeNull()
        ->and(abs($cookie->getExpiresTime() - now()->addDays(7)->getTimestamp()))->toBeLessThan(120);
});

it('sets no remember cookie unless asked', function () {
    $response = login(staff('editor')->email)->assertOk();

    expect(collect($response->headers->getCookies())->contains(fn ($c) => str_starts_with($c->getName(), 'remember_admin')))->toBeFalse();
});

it('regenerates the session on login and invalidates it on logout', function () {
    $user = staff('editor');
    $this->withSession(['probe' => 1]);
    $idAtLogin = null;
    Event::listen(Login::class, function () use (&$idAtLogin) {
        $idAtLogin = session()->getId(); // before Fortify prepares the session
    });

    login($user->email)->assertOk();
    $afterLogin = session()->getId();
    $this->postJson('/admin/logout')->assertNoContent();

    expect($idAtLogin)->not->toBeNull()->not->toBe($afterLogin)
        ->and(session()->getId())->not->toBe($afterLogin)
        ->and(session()->has('probe'))->toBeFalse() // invalidated, not just regenerated
        ->and(auth('admin')->check())->toBeFalse();
});

it('applies the session lifetime from the settings', function () {
    app(Settings::class)->set('security.session.lifetime_minutes', 45);
    // the provider reads it at boot: refresh the app the way the next request would
    $this->refreshApplication();

    expect(config('session.lifetime'))->toBe(45);
});

it('stores sessions in the database and lets a user list and revoke their own', function () {
    $user = staff('editor');
    $other = staff('editor');
    $now = time();
    DB::table('sessions')->insert([
        ['id' => 'mine-old', 'user_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Old phone', 'payload' => '', 'last_activity' => $now - 600],
        ['id' => 'theirs', 'user_id' => $other->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'Other', 'payload' => '', 'last_activity' => $now],
    ]);
    $this->actingAs($user, 'admin');

    $list = $this->getJson('/admin/sessions')->assertOk()->json('data');
    expect(collect($list)->pluck('id'))->toContain('mine-old')->not->toContain('theirs');

    $this->deleteJson('/admin/sessions/theirs')->assertNotFound(); // never someone else's
    expect(DB::table('sessions')->where('id', 'theirs')->exists())->toBeTrue();

    $this->deleteJson('/admin/sessions/mine-old')->assertOk();
    expect(DB::table('sessions')->where('id', 'mine-old')->exists())->toBeFalse();
});

it('signs out the other devices but not this one', function () {
    $user = staff('editor');
    DB::table('sessions')->insert([
        ['id' => 'phone', 'user_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Phone', 'payload' => '', 'last_activity' => time()],
        ['id' => 'other-device', 'user_id' => $user->id, 'ip_address' => '10.0.0.3', 'user_agent' => 'Tablet', 'payload' => '', 'last_activity' => time()],
    ]);
    $this->actingAs($user, 'admin');

    $this->deleteJson('/admin/sessions', ['password' => 'wrong-password-1'])->assertStatus(422);
    expect(DB::table('sessions')->where('id', 'phone')->exists())->toBeTrue();

    $this->deleteJson('/admin/sessions', ['password' => 'Secret-pass-123'])->assertOk(); // the two seeded rows, plus the throw-away session of the request that failed above
    expect(DB::table('sessions')->where('id', 'phone')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->where('id', 'other-device')->exists())->toBeFalse();
});

it('moves everything under the configured admin path', function () {
    app(Settings::class)->set('auth.admin_path', 'backstage');
    $this->refreshApplication();
    $user = staff('editor');

    $this->postJson('/backstage/login', ['email' => $user->email, 'password' => 'Secret-pass-123'])->assertOk();
    $this->postJson('/admin/login', ['email' => $user->email, 'password' => 'Secret-pass-123'])->assertNotFound();
    expect(route('admin.session', absolute: false))->toBe('/backstage/session');
});

it('refuses unsafe admin paths', function (string $path) {
    expect(fn () => app(Settings::class)->set('auth.admin_path', $path))->toThrow(InvalidSettingValue::class);
})->with(['../etc', 'a//b', 'api', 'Admin', '/', 'with space', '']);

it('lets only a super-admin change the admin path', function () {
    $manager = app(SettingsManager::class);

    $manager->update(staff('super-admin'), 'auth.admin_path', 'cms');
    expect(fn () => $manager->update(staff('admin'), 'auth.admin_path', 'other'))->toThrow(AuthorizationException::class)
        ->and(app(Settings::class)->get('auth.admin_path'))->toBe('cms');
});
