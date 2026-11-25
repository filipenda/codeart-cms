<?php

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Actions\DeactivateUser;
use Codeart\Core\Auth\Actions\SyncUserRoles;
use Codeart\Core\Settings\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

function events(): array
{
    return Activity::query()->orderBy('id')->pluck('event')->all();
}

it('records sign-in, failed sign-in and sign-out with ip and user agent', function () {
    $user = staff('editor');

    $this->postJson('/admin/login', ['email' => $user->email, 'password' => 'nope-nope-1'])->assertStatus(422);
    $this->postJson('/admin/login', ['email' => $user->email, 'password' => 'Secret-pass-123'])->assertOk();
    $this->postJson('/admin/logout')->assertNoContent();

    expect(events())->toContain('auth.login_failed', 'auth.login', 'auth.logout');
    $login = Activity::query()->where('event', 'auth.login')->firstOrFail();
    expect($login->causer_id)->toBe($user->id)->and($login->subject_id)->toBe($user->id)
        ->and($login->ip_address)->toBe('127.0.0.1')->and($login->user_agent)->not->toBeNull()
        ->and($login->log_name)->toBe('auth');
    $failed = Activity::query()->where('event', 'auth.login_failed')->firstOrFail();
    expect($failed->properties['email'])->toBe($user->email)->and($failed->causer_id)->toBeNull();
});

it('records a lockout', function () {
    app(Settings::class)->set('security.login.max_attempts', 1);
    $user = staff('editor');

    $this->postJson('/admin/login', ['email' => $user->email, 'password' => 'nope-nope-1']);
    $this->postJson('/admin/login', ['email' => $user->email, 'password' => 'nope-nope-1'])->assertStatus(429);

    expect(events())->toContain('auth.lockout');
});

it('keeps text typed into the e-mail field out of the log', function () {
    $this->postJson('/admin/login', ['email' => 'my-secret-password-1', 'password' => 'x'])->assertStatus(422);

    $entry = Activity::query()->where('event', 'auth.login_failed')->firstOrFail();
    expect($entry->properties['email'])->toBeNull()->and($entry->toJson())->not->toContain('my-secret-password-1');
});

it('records password reset requested and completed, and password changed', function () {
    Mail::fake();
    $user = staff('editor');

    $this->postJson('/admin/forgot-password', ['email' => $user->email])->assertOk();
    $token = Password::broker()->createToken($user);
    $this->postJson('/admin/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'])->assertOk();
    $this->actingAs($user->fresh(), 'admin')->putJson('/admin/user/password', ['current_password' => 'Brand-new-pass-7', 'password' => 'Another-pass-88', 'password_confirmation' => 'Another-pass-88'])->assertOk();

    expect(events())->toContain('password.reset_requested', 'password.reset_completed', 'password.changed');
});

it('records two-factor enabled, disabled and recovery codes regenerated', function () {
    $user = staff('editor');
    $this->actingAs($user, 'admin')->withSession(['auth.password_confirmed_at' => time()]);

    $this->postJson('/admin/user/two-factor-authentication')->assertOk();
    $this->postJson('/admin/user/confirmed-two-factor-authentication', ['code' => totp($user)])->assertOk();
    $this->postJson('/admin/user/two-factor-recovery-codes')->assertOk();
    $this->deleteJson('/admin/user/two-factor-authentication')->assertOk();

    expect(events())->toContain('two_factor.enabled', 'two_factor.recovery_codes_regenerated', 'two_factor.disabled');
});

it('records role changes', function () {
    $actor = staff('super-admin');
    $user = staff('editor');
    Activity::query()->delete();

    app(SyncUserRoles::class)($user, ['admin'], $actor);

    $roleEvents = Activity::query()->where('event', 'like', 'role.%')->get();
    expect($roleEvents->pluck('event')->all())->toContain('role.attached')
        ->and($roleEvents->firstWhere('event', 'role.attached')->properties['roles'])->toBe(['admin'])
        ->and($roleEvents->firstWhere('event', 'role.attached')->subject_id)->toBe($user->id);
});

it('records a permission change', function () {
    Activity::query()->delete();

    $user = staff('editor');
    $user->givePermissionTo('users.view');

    expect(Activity::query()->where('event', 'permission.attached')->exists())->toBeTrue();
});

it('records a deactivation and ends the user\'s sessions', function () {
    $user = staff('editor');
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    app(DeactivateUser::class)($user, staff('admin'));

    expect($user->fresh()->is_active)->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(events())->toContain('user.deactivated');
});

it('records a user created from the command line', function () {
    $this->artisan('user:create', ['name' => 'Ana', 'email' => 'ana@example.com', 'role' => 'editor'])
        ->expectsQuestion('Password', 'Correct-horse-9')->expectsQuestion('Confirm password', 'Correct-horse-9')->assertSuccessful();

    expect(events())->toContain('user.created');
});

it('never stores passwords, tokens, secrets or recovery codes', function () {
    $user = staff('editor');
    $this->actingAs($user, 'admin')->withSession(['auth.password_confirmed_at' => time()]);
    $this->postJson('/admin/user/two-factor-authentication')->assertOk();
    $this->postJson('/admin/user/confirmed-two-factor-authentication', ['code' => totp($user)])->assertOk();
    $secret = decrypt($user->fresh()->two_factor_secret);
    $codes = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);
    $this->postJson('/admin/login', ['email' => $user->email, 'password' => 'Secret-pass-123']);
    $this->postJson('/admin/login', ['email' => $user->email, 'password' => 'wrong-Password-77']);

    // a careless caller cannot put secrets in either
    app(ActivityLog::class)->record('test.event', 'Test', null, null, [
        'password' => 'hunter2-hunter2', 'token' => 'abc-token-123', 'nested' => ['recovery_codes' => ['aaaa-bbbb'], 'two_factor_secret' => 'zzzzz', 'ok' => 'visible'],
        'api_key' => 'key-123', 'cookie' => 'c', 'authorization' => 'Bearer x',
    ]);

    $dump = Activity::query()->get()->toJson();
    foreach (['Secret-pass-123', 'wrong-Password-77', $secret, $codes[0], 'hunter2-hunter2', 'abc-token-123', 'aaaa-bbbb', 'zzzzz', 'key-123', 'Bearer x'] as $forbidden) {
        expect($dump)->not->toContain($forbidden);
    }
    expect($dump)->toContain('visible');
    $stored = Activity::query()->where('event', 'test.event')->firstOrFail();
    expect($stored->properties->all())->toHaveKey('nested.ok')->not->toHaveKey('password');
});

it('prunes entries older than the retention setting', function () {
    app(Settings::class)->set('security.activity_log.retention_days', 30);
    Activity::query()->delete(); // the role seeding of the test setup is logged too
    app(ActivityLog::class)->record('old', 'Old');
    app(ActivityLog::class)->record('recent', 'Recent');
    app(ActivityLog::class)->record('borderline', 'Borderline');
    Activity::query()->where('event', 'old')->update(['created_at' => now()->subDays(31)]);
    Activity::query()->where('event', 'borderline')->update(['created_at' => now()->subDays(29)]);

    $this->artisan('activity-log:prune')->assertSuccessful();

    expect(events())->toEqualCanonicalizing(['recent', 'borderline']);
});

it('is scheduled daily', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'activity-log:prune'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 0 * * *');
});

it('does not break a sign-in when logging fails', function () {
    Schema::drop('activity_log');
    $user = staff('editor');

    $this->postJson('/admin/login', ['email' => $user->email, 'password' => 'Secret-pass-123'])->assertOk();
});
