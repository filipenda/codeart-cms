<?php

use Codeart\Core\Events\ModuleDisabled;
use Codeart\Core\Events\ModuleEnabled;
use Codeart\Core\Modules\Actions\DisableModule;
use Codeart\Core\Modules\Actions\EnableModule;
use Codeart\Core\Modules\ModuleDiscovery;
use Codeart\Core\Modules\ModuleException;
use Codeart\Core\Modules\ModuleRecord;
use Codeart\Core\Modules\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

function enable(string $name): bool
{
    return app(EnableModule::class)($name);
}

function disable(string $name): bool
{
    return app(DisableModule::class)($name);
}

it('discovers modules with the version from installed metadata, never from module.json', function () {
    useModules(['alpha' => '1.2.0', 'broken' => '1.0.0']);

    $registry = app(ModuleRegistry::class);

    expect(array_keys($registry->installed()))->toBe(['alpha'])
        ->and($registry->find('alpha')->version)->toBe('1.2.0')
        ->and($registry->problems())->toHaveKey('fixture/module-broken');
});

it('reads real Composer metadata without failing (no module packages in core itself)', function () {
    expect((new ModuleDiscovery)->discover())->toBe([]);
});

it('starts with every module disabled', function () {
    useModules(['alpha' => '1.2.0']);

    expect(app(ModuleRegistry::class)->enabledNames())->toBe([])
        ->and(Schema::hasTable('alpha_items'))->toBeFalse();
});

it('enables a module: state, migration, permissions, event', function () {
    useModules(['alpha' => '1.2.0']);
    $events = [];
    Event::listen(ModuleEnabled::class, function (ModuleEnabled $e) use (&$events) {
        $events[] = [$e->module->name, $e->version];
    });

    expect(enable('alpha'))->toBeTrue();

    $record = ModuleRecord::where('name', 'alpha')->first();
    expect($record->enabled)->toBeTrue()
        ->and($record->package)->toBe('fixture/module-alpha')
        ->and($record->installed_version)->toBe('1.2.0')
        ->and($record->enabled_at)->not->toBeNull()
        ->and(Schema::hasTable('alpha_items'))->toBeTrue()
        ->and(Permission::pluck('name')->sort()->values()->all())->toBe(['alpha.edit', 'alpha.view'])
        ->and($events)->toBe([['alpha', '1.2.0']])
        ->and(app(ModuleRegistry::class)->isEnabled('alpha'))->toBeTrue();
});

it('runs a module migration only once, even across disable and enable', function () {
    useModules(['alpha' => '1.2.0']);

    enable('alpha');
    DB::table('alpha_items')->insert(['title' => 'keep me']);
    disable('alpha');
    enable('alpha');
    expect(enable('alpha'))->toBeFalse();

    expect(DB::table('migrations')->where('migration', 'like', '%create_alpha_items_table')->count())->toBe(1)
        ->and(DB::table('alpha_items')->count())->toBe(1);
});

it('does not duplicate permissions when enabled again', function () {
    useModules(['alpha' => '1.2.0']);

    enable('alpha');
    disable('alpha');
    enable('alpha');

    expect(Permission::count())->toBe(2);
});

it('disables without deleting data, tables or permissions, and fires ModuleDisabled', function () {
    useModules(['alpha' => '1.2.0']);
    $fired = [];
    Event::listen(ModuleDisabled::class, function (ModuleDisabled $e) use (&$fired) {
        $fired[] = $e->module->name;
    });
    enable('alpha');
    DB::table('alpha_items')->insert(['title' => 'x']);

    expect(disable('alpha'))->toBeTrue()
        ->and(disable('alpha'))->toBeFalse()
        ->and($fired)->toBe(['alpha'])
        ->and(ModuleRecord::where('name', 'alpha')->first()->enabled)->toBeFalse()
        ->and(app(ModuleRegistry::class)->isEnabled('alpha'))->toBeFalse()
        ->and(DB::table('alpha_items')->count())->toBe(1)
        ->and(Permission::count())->toBe(2);
});

it('refuses to enable a module that is not installed', function () {
    useModules([]);

    expect(fn () => enable('alpha'))->toThrow(ModuleException::class, 'not installed');
});

it('refuses to enable when a required module is missing', function () {
    useModules(['beta' => '1.0.0']);

    expect(fn () => enable('beta'))->toThrow(ModuleException::class, 'requires module "alpha" which is not installed');
    expect(ModuleRecord::count())->toBe(0);
});

it('refuses to enable when a required module is disabled', function () {
    useModules(['alpha' => '1.2.0', 'beta' => '1.0.0']);

    expect(fn () => enable('beta'))->toThrow(ModuleException::class, 'which is disabled');
    expect(ModuleRecord::count())->toBe(0);
});

it('refuses to enable when the installed version does not satisfy the constraint', function () {
    useModules(['alpha' => '1.2.0', 'gamma' => '1.0.0']);
    enable('alpha');

    expect(fn () => enable('gamma'))->toThrow(ModuleException::class, '^2.0 but 1.2.0 is installed');
});

it('skips version constraints for dev installs (path repositories)', function () {
    useModules(['alpha' => 'dev-main', 'gamma' => '1.0.0']);
    enable('alpha');

    expect(enable('gamma'))->toBeTrue();
});

it('enables a dependent once its requirement is enabled', function () {
    useModules(['alpha' => '1.2.0', 'beta' => '1.0.0']);
    enable('alpha');

    expect(enable('beta'))->toBeTrue();
});

it('refuses to disable a module another enabled module requires', function () {
    useModules(['alpha' => '1.2.0', 'beta' => '1.0.0']);
    enable('alpha');
    enable('beta');

    expect(fn () => disable('alpha'))->toThrow(ModuleException::class, 'enabled module "beta" requires it');
    expect(app(ModuleRegistry::class)->isEnabled('alpha'))->toBeTrue();

    disable('beta');
    expect(disable('alpha'))->toBeTrue();
});

it('has no routes while disabled and registers them after enabling', function () {
    useModules(['alpha' => '1.2.0']);
    expect(Route::has('alpha.index'))->toBeFalse();
    $this->get('/alpha')->assertNotFound();

    enable('alpha');

    expect(Route::has('alpha.index'))->toBeTrue();
});

it('boots only enabled modules in a fresh application', function () {
    useModules(['alpha' => '1.2.0', 'beta' => '1.0.0']);
    enable('alpha');

    $this->refreshApplication();
    useModules(['alpha' => '1.2.0', 'beta' => '1.0.0']);
    app(ModuleRegistry::class)->bootEnabled();

    expect(Route::has('alpha.index'))->toBeTrue()
        ->and(app('migrator')->paths())->toContain(dirname(__DIR__, 2).'/Fixtures/modules/alpha/database/migrations');

    disable('alpha');
    $this->refreshApplication();
    useModules(['alpha' => '1.2.0', 'beta' => '1.0.0']);
    app(ModuleRegistry::class)->bootEnabled();

    expect(Route::has('alpha.index'))->toBeFalse()
        ->and(app('migrator')->paths())->not->toContain(dirname(__DIR__, 2).'/Fixtures/modules/alpha/database/migrations');
    $this->get('/alpha')->assertNotFound();
});

it('caches the enabled list and refreshes it on enable and disable', function () {
    useModules(['alpha' => '1.2.0']);
    $registry = app(ModuleRegistry::class);
    expect($registry->enabledNames())->toBe([]);

    expect(cache()->get(ModuleRegistry::CACHE_KEY))->toBe([]);

    enable('alpha');
    expect(cache()->has(ModuleRegistry::CACHE_KEY))->toBeFalse()
        ->and($registry->enabledNames())->toBe(['alpha'])
        ->and(cache()->get(ModuleRegistry::CACHE_KEY))->toBe(['alpha']);

    ModuleRecord::create(['name' => 'ghost', 'package' => 'x/y', 'enabled' => true]); // behind the cache's back
    expect($registry->enabledNames())->toBe(['alpha']);

    disable('alpha');
    expect(cache()->has(ModuleRegistry::CACHE_KEY))->toBeFalse();
});

it('does not crash and caches nothing when the database is not ready', function () {
    useModules(['alpha' => '1.2.0']);
    Schema::drop('modules');
    cache()->forget(ModuleRegistry::CACHE_KEY);

    $registry = app(ModuleRegistry::class);

    expect($registry->enabledNames())->toBe([])
        ->and($registry->records())->toBe([])
        ->and(cache()->has(ModuleRegistry::CACHE_KEY))->toBeFalse();
    $registry->bootEnabled();
    expect(fn () => enable('alpha'))->toThrow(ModuleException::class, '"modules" table does not exist');
});

it('lists modules and enables/disables them from the CLI', function () {
    useModules(['alpha' => '1.2.0', 'beta' => '1.0.0']);

    $this->artisan('module:enable beta')->expectsOutputToContain('requires module "alpha" which is disabled')->assertFailed();
    $this->artisan('module:enable alpha')->expectsOutputToContain('enabled')->assertSuccessful();
    $this->artisan('module:enable alpha')->expectsOutputToContain('already enabled')->assertSuccessful();
    $this->artisan('module:enable nope')->expectsOutputToContain('not installed')->assertFailed();
    $this->artisan('module:list')->expectsOutputToContain('fixture/module-alpha')->assertSuccessful();
    $this->artisan('module:enable beta')->assertSuccessful();
    $this->artisan('module:disable alpha')->expectsOutputToContain('requires it')->assertFailed();
    $this->artisan('module:disable beta')->assertSuccessful();
    $this->artisan('module:disable alpha')->assertSuccessful();
});
