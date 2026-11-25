<?php

namespace Codeart\Core\Modules\Actions;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Events\ModuleEnabled;
use Codeart\Core\Modules\InstalledModule;
use Codeart\Core\Modules\ModuleException;
use Codeart\Core\Modules\ModuleRecord;
use Codeart\Core\Modules\ModuleRegistry;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class EnableModule
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    /** @return bool false when the module was already enabled (nothing done) */
    public function __invoke(string $name): bool
    {
        $module = $this->registry->find($name)
            ?? throw new ModuleException("Module \"{$name}\" is not installed. Install its Composer package first.");

        if ($this->registry->isEnabled($name)) {
            return false;
        }

        $this->assertDependencies($module);

        if (! Schema::hasTable('modules')) {
            throw new ModuleException('The "modules" table does not exist yet. Run "php artisan migrate" first.');
        }

        $this->migrate($module);
        $this->syncPermissions($module);

        ModuleRecord::query()->updateOrCreate(['name' => $name], [
            'package' => $module->manifest->package,
            'enabled' => true,
            'installed_version' => $module->version,
            'enabled_at' => now(),
        ]);
        $this->registry->forgetCache();

        $this->registry->boot($module);
        event(new ModuleEnabled($module->manifest, $module->version));

        return true;
    }

    private function assertDependencies(InstalledModule $module): void
    {
        foreach ($module->manifest->requires as $required => $constraint) {
            $dependency = $this->registry->find($required);
            if ($dependency === null) {
                throw new ModuleException("Cannot enable \"{$module->manifest->name}\": it requires module \"{$required}\" which is not installed.");
            }
            if (! $this->registry->isEnabled($required)) {
                throw new ModuleException("Cannot enable \"{$module->manifest->name}\": it requires module \"{$required}\" which is disabled. Enable \"{$required}\" first.");
            }
            if (! $this->satisfies($dependency->version, $constraint)) {
                throw new ModuleException("Cannot enable \"{$module->manifest->name}\": it requires \"{$required}\" {$constraint} but {$dependency->version} is installed.");
            }
        }
    }

    private function satisfies(string $version, string $constraint): bool
    {
        // Path/dev installs (dev-main, 1.x-dev) have no real version to compare.
        if (str_starts_with($version, 'dev-') || str_ends_with($version, '-dev')) {
            return true;
        }

        try {
            return Semver::satisfies($version, $constraint);
        } catch (InvalidArgumentException $e) {
            throw new ModuleException("Invalid version constraint \"{$constraint}\": {$e->getMessage()}");
        }
    }

    private function migrate(InstalledModule $module): void
    {
        $dir = $module->manifest->migrations;
        if ($dir === null) {
            return;
        }
        $path = $module->path.'/'.$dir;
        if (! is_dir($path)) {
            throw new ModuleException("Module \"{$module->manifest->name}\": migrations path \"{$dir}\" does not exist.");
        }

        // The migrations table remembers what ran, so enabling again never repeats a migration.
        Artisan::call('migrate', ['--path' => $path, '--realpath' => true, '--force' => true]);
    }

    private function syncPermissions(InstalledModule $module): void
    {
        $guard = Admin::guardName();
        foreach ($module->manifest->permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
