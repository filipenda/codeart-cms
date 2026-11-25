<?php

use Codeart\Core\Modules\MenuRegistry;
use Codeart\Core\Modules\ModuleDiscovery;
use Codeart\Core\Modules\ModuleRegistry;
use Codeart\Core\Permissions\CreateDefaultRoles;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Tests\AuthTestCase;
use Codeart\Core\Tests\Support\AdminTestUser;
use Codeart\Core\Tests\Support\FakeDiscovery;
use Codeart\Core\Tests\Support\StubUser;
use Codeart\Core\Tests\TestCase;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;

uses(TestCase::class)->in('Unit', 'Feature');
uses(AuthTestCase::class)->in('Auth');

pest()->beforeEach(function () {
    // Enabling a module runs DDL (implicit commits), so no transaction-based refresh: rebuild from scratch.
    $this->artisan('migrate:fresh', ['--force' => true]);
    // The app booted against the previous test's leftovers and cached them; start clean.
    app(ModuleRegistry::class)->forgetCache();
    FakeDiscovery::$modules = [];
    // Core does not own users; the stub user needs a table.
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
})->in('Feature/Modules');

// Settings tests: fresh schema, roles, and no cached leftovers.
pest()->beforeEach(function () {
    $this->artisan('migrate:fresh', ['--force' => true]);
    app(ModuleRegistry::class)->forgetCache();
    app(Settings::class)->flush();
    FakeDiscovery::$modules = [];
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    app(CreateDefaultRoles::class)();
})->in('Feature/Settings');

// Auth tests: fresh schema (framework users + sessions + core's columns), roles and permissions, no real HTTP.
pest()->beforeEach(function () {
    $this->artisan('migrate:fresh', ['--force' => true]);
    app(ModuleRegistry::class)->forgetCache();
    app(Settings::class)->flush();
    FakeDiscovery::$modules = [];
    app(CreateDefaultRoles::class)();
    fakeHttp(['api.pwnedpasswords.com/*' => Http::response('', 200)]); // nobody is "breached" unless a test says so
})->in('Auth');

// Settings written by a test must not leak into the next application boot (the admin path is read at boot).
pest()->afterEach(fn () => Schema::hasTable('settings') && DB::table('settings')->delete())->in('Auth');

/** Replace any earlier Http::fake (the first matching stub wins, so a later fake() could not override it). */
function fakeHttp(array $stubs): void
{
    Http::swap(new Factory);
    Http::fake($stubs);
}

/**
 * Make the next requests continue the session of the last one (the test client keeps no cookies).
 * Cookie encryption is switched off for the test so the plain session id can be sent.
 */
function carrySession(): void
{
    test()->withoutMiddleware(EncryptCookies::class)->withCookie(config('session.cookie'), session()->getId());
    app('auth')->forgetGuards(); // state must come from the session, not from memory
}

/** The code of the current 30-second window, or of a later one ($windows ahead): a used code is refused again. */
function totp(AdminTestUser $user, int $windows = 0): string
{
    $google = app(Google2FA::class);

    return $google->oathTotp(decrypt($user->fresh()->two_factor_secret), $google->getTimestamp() + $windows);
}

/** A staff user with a role, ready to sign in (password Secret-pass-123), approved, verified and active unless said otherwise. */
function staff(string $role = 'editor', array $attributes = []): AdminTestUser
{
    $user = AdminTestUser::create(array_merge([
        'name' => ucfirst($role),
        'email' => $role.'-'.uniqid().'@example.com',
        'password' => 'Secret-pass-123',
        'email_verified_at' => now(),
        'approved_at' => now(),
        'is_active' => true,
    ], $attributes));
    $user->assignRole($role);

    return $user->fresh();
}

function userWith(string $role): StubUser
{
    $user = StubUser::create(['name' => $role]);
    $user->assignRole($role);

    return $user;
}

/**
 * Pretend these fixture modules are installed (dir => version) and rebuild the registries.
 *
 * @param  array<string, string>  $modules
 */
function useModules(array $modules): void
{
    FakeDiscovery::$modules = $modules;
    foreach ([ModuleDiscovery::class, ModuleRegistry::class, MenuRegistry::class] as $class) {
        app()->forgetInstance($class);
    }
}
