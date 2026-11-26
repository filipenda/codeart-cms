<?php

namespace Codeart\Core\Permissions;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Settings\CoreSettings;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreateDefaultRoles
{
    public const array ROLES = ['super-admin', 'admin', 'editor', 'member'];

    /**
     * Permissions core owns, and the roles that get them when a permission is first created.
     * `member` (registered users) and `super-admin` (passes through the Gate) get none.
     * Granted only on creation, so a later revoke sticks when this runs again.
     */
    public const array PERMISSIONS = [
        CoreSettings::PERMISSION_APPEARANCE => ['admin'],
        'admin.access' => ['admin', 'editor'],
        'system.status' => ['admin'],
        'users.view' => ['admin'],
        'users.invite' => ['admin'],
        'users.manage' => ['admin'],
        'users.approve' => ['admin'],
    ];

    /** @return list<string> roles that were created (existing ones are left alone) */
    public function __invoke(): array
    {
        $guard = Admin::guardName();
        $created = [];
        foreach (self::ROLES as $name) {
            $role = Role::findOrCreate($name, $guard);
            if ($role->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        foreach (self::PERMISSIONS as $name => $roles) {
            $permission = Permission::findOrCreate($name, $guard);
            if ($permission->wasRecentlyCreated) {
                foreach ($roles as $role) {
                    Role::findByName($role, $guard)->givePermissionTo($permission);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }
}
