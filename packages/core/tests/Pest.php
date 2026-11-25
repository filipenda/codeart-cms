<?php

use Codeart\Core\Modules\MenuRegistry;
use Codeart\Core\Modules\ModuleDiscovery;
use Codeart\Core\Modules\ModuleRegistry;
use Codeart\Core\Permissions\CreateDefaultRoles;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Tests\Support\FakeDiscovery;
use Codeart\Core\Tests\Support\StubUser;
use Codeart\Core\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class)->in('Unit', 'Feature');

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
