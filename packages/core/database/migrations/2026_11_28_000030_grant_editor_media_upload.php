<?php

use Codeart\Core\Auth\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Existing installs: editors may view and upload media (to insert images while writing content).
 * Runs once (a later revoke sticks); new installs get the same through CreateDefaultRoles. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $guard = Admin::guardName();
        $editor = Role::query()->where('name', 'editor')->where('guard_name', $guard)->first();
        if ($editor === null) {
            return; // roles do not exist yet: codeart:roles will create them with the new defaults
        }

        foreach (['media.view', 'media.upload'] as $name) {
            $editor->givePermissionTo(Permission::findOrCreate($name, $guard));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
