<?php

namespace Codeart\Core\Permissions;

use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreateDefaultRoles
{
    public const array ROLES = ['super-admin', 'admin', 'editor'];

    /** @return list<string> roles that were created (existing ones are left alone) */
    public function __invoke(): array
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $created = [];
        foreach (self::ROLES as $name) {
            $role = Role::findOrCreate($name, $guard);
            if ($role->wasRecentlyCreated) {
                $created[] = $name;
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }
}
