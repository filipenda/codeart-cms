<?php

namespace Codeart\Core\Permissions;

use Codeart\Core\Auth\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Default role grants of a module, applied when it is enabled: `['admin' => ['view', 'update'], 'editor' => ['view']]`.
 * A role that already holds any permission of the module is left alone, so a revoke made later sticks when the
 * module is disabled and enabled again. `super-admin` needs nothing (it passes every check).
 */
final class GrantModulePermissions
{
    /** @param  array<string, list<string>>  $grants role => actions (without the `<module>.` prefix) */
    public function __invoke(string $module, array $grants): void
    {
        $guard = Admin::guardName();
        foreach ($grants as $roleName => $actions) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();
            if ($role === null || $role->permissions()->where('name', 'like', $module.'.%')->exists()) {
                continue;
            }
            foreach ($actions as $action) {
                $role->givePermissionTo(Permission::findOrCreate($module.'.'.$action, $guard));
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
