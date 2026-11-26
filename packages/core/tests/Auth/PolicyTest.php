<?php

use Codeart\Core\Auth\Actions\CreateAdminUser;
use Codeart\Core\Auth\Actions\DeactivateUser;
use Codeart\Core\Auth\Actions\SyncUserRoles;
use Codeart\Core\Permissions\CreateDefaultRoles;
use Codeart\Core\Tests\Support\AdminTestUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

it('grants the permissions to the roles it should', function () {
    $permissions = fn (string $role) => Role::findByName($role, 'admin')->permissions->pluck('name')->sort()->values()->all();

    expect($permissions('admin'))->toBe(['admin.access', 'settings.appearance', 'system.status', 'users.approve', 'users.invite', 'users.manage', 'users.view'])
        ->and($permissions('editor'))->toBe(['admin.access'])
        ->and($permissions('member'))->toBe([])
        ->and($permissions('super-admin'))->toBe([]); // passes through the Gate instead
});

it('does not hand a revoked permission back when the roles command runs again', function () {
    Role::findByName('admin', 'admin')->revokePermissionTo('users.manage');

    app(CreateDefaultRoles::class)();

    expect(Role::findByName('admin', 'admin')->hasPermissionTo('users.manage'))->toBeFalse();
});

it('lets super-admin do everything and member nothing', function (string $ability) {
    $target = staff('editor');

    expect(Gate::forUser(staff('super-admin'))->allows($ability, $target))->toBeTrue()
        ->and(Gate::forUser(staff('member'))->allows($ability, $target))->toBeFalse()
        ->and(Gate::forUser(staff('editor'))->allows($ability, $target))->toBeFalse();
})->with(['viewAny', 'view', 'update', 'deactivate', 'approve', 'delete']);

it('lets an admin manage users below them, but nobody above or the super-admin', function (string $targetRole, bool $allowed) {
    $admin = staff('admin');
    $target = staff($targetRole);

    foreach (['update', 'deactivate', 'approve'] as $ability) {
        expect(Gate::forUser($admin)->allows($ability, $target))->toBe($allowed, "{$ability} {$targetRole}");
    }
})->with([
    'member' => ['member', true],
    'editor' => ['editor', true],
    'admin' => ['admin', true],
    'super-admin' => ['super-admin', false],
]);

it('does not let an admin deactivate themselves', function () {
    $admin = staff('admin');

    expect(Gate::forUser($admin)->allows('deactivate', $admin))->toBeFalse();
    expect(fn () => app(DeactivateUser::class)($admin, $admin))->toThrow(AuthorizationException::class);
    expect(fn () => app(DeactivateUser::class)(staff('super-admin'), staff('super-admin')))->not->toThrow(Throwable::class); // another super-admin may
});

it('never lets anyone but a super-admin touch a super-admin', function () {
    $superAdmin = staff('super-admin');
    $admin = staff('admin');

    expect(fn () => app(DeactivateUser::class)($superAdmin, $admin))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SyncUserRoles::class)($superAdmin, ['editor'], $admin))->toThrow(AuthorizationException::class);
    expect($superAdmin->fresh()->is_active)->toBeTrue()->and($superAdmin->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('never lets an admin hand out the super-admin role, to anyone', function () {
    $admin = staff('admin');
    $editor = staff('editor');

    expect(fn () => app(SyncUserRoles::class)($editor, ['super-admin'], $admin))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SyncUserRoles::class)($admin, ['super-admin'], $admin))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CreateAdminUser::class)('X', 'x@example.com', 'Correct-horse-9', ['super-admin'], $admin))->toThrow(AuthorizationException::class);
    expect($editor->fresh()->hasRole('super-admin'))->toBeFalse()
        ->and(AdminTestUser::query()->where('email', 'x@example.com')->exists())->toBeFalse();
});

it('lets an admin change roles up to their own rank', function () {
    $admin = staff('admin');
    $editor = staff('editor');

    app(SyncUserRoles::class)($editor, ['member'], $admin);
    expect($editor->fresh()->getRoleNames()->all())->toBe(['member']);
    app(SyncUserRoles::class)($editor, ['admin'], $admin);
    expect($editor->fresh()->hasRole('admin'))->toBeTrue();
});

it('lets only a super-admin hand out roles it does not know', function () {
    Role::findOrCreate('custom', 'admin');
    $editor = staff('editor');

    expect(fn () => app(SyncUserRoles::class)($editor, ['custom'], staff('admin')))->toThrow(AuthorizationException::class);
    app(SyncUserRoles::class)($editor, ['custom'], staff('super-admin'));
    expect($editor->fresh()->hasRole('custom'))->toBeTrue();
});

it('lets a super-admin remove another super-admin\'s role', function () {
    $other = staff('super-admin');

    app(SyncUserRoles::class)($other, ['editor'], staff('super-admin'));

    expect($other->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('keeps users.* permissions away from editors even when they try the actions', function () {
    $editor = staff('editor');

    expect(fn () => app(SyncUserRoles::class)(staff('member'), ['editor'], $editor))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeactivateUser::class)(staff('member'), $editor))->toThrow(AuthorizationException::class);
});
