<?php

use App\Models\User;
use Codeart\Core\Modules\MenuRegistry;
use Codeart\Core\Modules\ModuleRegistry;
use Codeart\Core\Permissions\CreateDefaultRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

// Enabling runs migrations (DDL, implicit commits), so these tests rebuild the database
// instead of using RefreshDatabase's transaction (see tests/Pest.php).

beforeEach(function () {
    $this->artisan('migrate:fresh', ['--force' => true]);
    app(ModuleRegistry::class)->forgetCache();
    app(CreateDefaultRoles::class)();
});

function userWith(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('discovers the example module through the path repository', function () {
    $module = app(ModuleRegistry::class)->find('example');

    expect($module)->not->toBeNull()
        ->and($module->manifest->package)->toBe('codeart/module-example')
        ->and($module->version)->not->toBe('unknown');
});

it('is disabled by default: no table, no route, no menu', function () {
    expect(Schema::hasTable('example_items'))->toBeFalse()
        ->and(Route::has('example.index'))->toBeFalse()
        ->and(app(MenuRegistry::class)->items(userWith('super-admin')))->toBe([]);
});

it('enables end to end: table, permissions, listener, route, menu', function () {
    $this->artisan('module:enable example')->assertSuccessful();

    expect(Schema::hasTable('example_items'))->toBeTrue()
        ->and(Permission::pluck('name')->sort()->values()->all())->toBe(['example.manage', 'example.view'])
        ->and(DB::table('example_items')->pluck('title')->all())->toBe(['Welcome item']) // seeded by the ModuleEnabled listener
        ->and(Route::has('example.index'))->toBeTrue();

    $editor = userWith('editor');
    expect(array_map(fn ($i) => $i->label, app(MenuRegistry::class)->items($editor)))->toBe([]);

    $editor->givePermissionTo('example.view');
    expect(array_map(fn ($i) => $i->label, app(MenuRegistry::class)->items($editor->fresh())))->toBe(['Example']);
});

it('protects the route with the example.view permission', function () {
    $this->artisan('module:enable example')->assertSuccessful();

    $this->get('/example')->assertForbidden();
    $this->actingAs(userWith('editor'))->get('/example')->assertForbidden();

    $viewer = userWith('editor');
    $viewer->givePermissionTo('example.view');
    $this->actingAs($viewer->fresh())->get('/example')->assertOk()->assertJson(['items' => ['Welcome item']]);
    $this->actingAs(userWith('super-admin'))->get('/example')->assertOk();
});

it('keeps data and permissions when disabled, and does not seed twice on re-enable', function () {
    $this->artisan('module:enable example')->assertSuccessful();
    $this->artisan('module:disable example')->assertSuccessful();

    expect(Schema::hasTable('example_items'))->toBeTrue()
        ->and(DB::table('example_items')->count())->toBe(1)
        ->and(Permission::count())->toBe(2)
        ->and(app(ModuleRegistry::class)->isEnabled('example'))->toBeFalse();

    $this->artisan('module:enable example')->assertSuccessful();
    expect(DB::table('example_items')->count())->toBe(1);
});

it('allows managing modules only for super-admin', function () {
    expect(Gate::forUser(userWith('super-admin'))->allows('manage-modules'))->toBeTrue()
        ->and(Gate::forUser(userWith('admin'))->allows('manage-modules'))->toBeFalse()
        ->and(Gate::forUser(userWith('editor'))->allows('manage-modules'))->toBeFalse();
});
