<?php

use Codeart\Core\Modules\Actions\EnableModule;
use Codeart\Core\Modules\MenuRegistry;
use Codeart\Core\Permissions\CreateDefaultRoles;
use Codeart\Core\Tests\Support\StubUser;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

function userWithRole(string $role): StubUser
{
    app(CreateDefaultRoles::class)();
    $user = StubUser::create(['name' => $role]);
    $user->assignRole($role);

    return $user;
}

function labels(?StubUser $user): array
{
    return array_map(fn ($i) => $i->label, app(MenuRegistry::class)->items($user));
}

it('creates the default roles, once', function () {
    expect(app(CreateDefaultRoles::class)())->toBe(['super-admin', 'admin', 'editor', 'member'])
        ->and(app(CreateDefaultRoles::class)())->toBe([])
        ->and(Role::pluck('name')->sort()->values()->all())->toBe(['admin', 'editor', 'member', 'super-admin']);

    $this->artisan('codeart:roles')->expectsOutputToContain('already exist')->assertSuccessful();
});

it('allows manage-modules only for super-admin', function (string $role, bool $allowed) {
    expect(Gate::forUser(userWithRole($role))->allows('manage-modules'))->toBe($allowed);
})->with([
    'super-admin' => ['super-admin', true],
    'admin' => ['admin', false],
    'editor' => ['editor', false],
]);

it('denies manage-modules to a user without a role and to guests', function () {
    $user = StubUser::create(['name' => 'plain']);

    expect(Gate::forUser($user)->allows('manage-modules'))->toBeFalse()
        ->and(Gate::allows('manage-modules'))->toBeFalse();
});

it('lets super-admin pass every check, other roles only what they were given', function () {
    useModules(['alpha' => '1.2.0']);
    app(EnableModule::class)('alpha');

    $super = userWithRole('super-admin');
    $admin = userWithRole('admin');

    expect($super->can('alpha.view'))->toBeTrue()
        ->and($super->can('anything.at.all'))->toBeTrue()
        ->and($admin->can('alpha.view'))->toBeFalse();

    $admin->givePermissionTo('alpha.view');
    expect($admin->fresh()->can('alpha.view'))->toBeTrue()
        ->and($admin->fresh()->can('alpha.edit'))->toBeFalse();
});

it('protects a module route with its permission', function () {
    useModules(['alpha' => '1.2.0']);
    app(EnableModule::class)('alpha');

    $this->actingAs(StubUser::create(['name' => 'nobody']))->get('/alpha')->assertForbidden();

    $editor = userWithRole('editor');
    $editor->givePermissionTo('alpha.view');
    $this->actingAs($editor->fresh())->get('/alpha')->assertOk()->assertSee('alpha ok');
    $this->actingAs(userWithRole('super-admin'))->get('/alpha')->assertOk();
});

it('returns the menu of enabled modules only, sorted by order', function () {
    useModules(['alpha' => '1.2.0', 'beta' => '1.0.0']);
    $super = userWithRole('super-admin');

    expect(labels($super))->toBe([]);

    app(EnableModule::class)('alpha');
    expect(labels($super))->toBe(['Alpha list', 'Alpha edit']);

    app(EnableModule::class)('beta');
    expect(labels($super))->toBe(['Beta', 'Alpha list', 'Alpha edit']);
});

it('filters the menu by the user\'s permissions', function () {
    useModules(['alpha' => '1.2.0', 'beta' => '1.0.0']);
    app(EnableModule::class)('alpha');
    app(EnableModule::class)('beta');

    $editor = userWithRole('editor');
    expect(labels($editor))->toBe(['Beta']);

    $editor->givePermissionTo('alpha.edit');
    expect(labels($editor->fresh()))->toBe(['Beta', 'Alpha edit']);

    expect(labels(null))->toBe(['Beta']);
});
