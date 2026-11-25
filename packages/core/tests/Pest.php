<?php

use Codeart\Core\Modules\MenuRegistry;
use Codeart\Core\Modules\ModuleDiscovery;
use Codeart\Core\Modules\ModuleRegistry;
use Codeart\Core\Tests\Support\FakeDiscovery;
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
