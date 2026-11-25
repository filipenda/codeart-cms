<?php

namespace Codeart\Core;

use Codeart\Core\Console\ModuleDisableCommand;
use Codeart\Core\Console\ModuleEnableCommand;
use Codeart\Core\Console\ModuleListCommand;
use Codeart\Core\Console\RolesCommand;
use Codeart\Core\Modules\MenuRegistry;
use Codeart\Core\Modules\ModuleDiscovery;
use Codeart\Core\Modules\ModuleRegistry;
use Codeart\Core\Theme\ThemeSettings;
use Codeart\Core\Translations\Locales;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Core guarantees roles/permissions; registering twice is harmless (Laravel de-duplicates).
        $this->app->register(PermissionServiceProvider::class);

        $this->app->singletonIf(ModuleDiscovery::class);
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(MenuRegistry::class);

        $this->mergeConfigFrom(__DIR__.'/../config/codeart.php', 'codeart');

        $this->app->singleton(Locales::class, fn (): Locales => new Locales((array) config('codeart.locales', [])));

        $this->app->singleton(ThemeSettings::class, fn (): ThemeSettings => new ThemeSettings(
            (array) config('codeart.theme', []),
            (array) config('codeart.theme_base', []),
            (array) config('codeart.theme_defaults', []),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'codeart');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Only a super-admin may manage modules (future admin screen). The commands are CLI-only.
        // Super-admin passes every check, including permissions declared by modules.
        Gate::before(fn ($user) => $this->hasSuperAdminRole($user) ? true : null);
        Gate::define('manage-modules', fn ($user): bool => $this->hasSuperAdminRole($user));

        // Boot enabled modules once every provider is registered. Registering a provider after
        // the app has booted boots it immediately.
        $this->app->booted(fn () => $this->app->make(ModuleRegistry::class)->bootEnabled());

        if ($this->app->runningInConsole()) {
            $this->commands([ModuleListCommand::class, ModuleEnableCommand::class, ModuleDisableCommand::class, RolesCommand::class]);

            $this->publishes([
                __DIR__.'/../config/codeart.php' => config_path('codeart.php'),
            ], 'codeart-config');

            // Pre-built assets are shipped inside the package (no Node on servers).
            $this->publishes([
                __DIR__.'/../resources/dist' => public_path('vendor/codeart'),
            ], 'codeart-assets');
        }
    }

    private function hasSuperAdminRole(mixed $user): bool
    {
        return is_object($user) && method_exists($user, 'hasRole') && $user->hasRole('super-admin');
    }
}
