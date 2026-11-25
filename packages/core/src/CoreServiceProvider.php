<?php

namespace Codeart\Core;

use Codeart\Core\Auth\AuthServiceProvider;
use Codeart\Core\Console\ActivityLogPruneCommand;
use Codeart\Core\Console\BrandLoadCommand;
use Codeart\Core\Console\LangTransliterateCommand;
use Codeart\Core\Console\MailTestCommand;
use Codeart\Core\Console\ModuleDisableCommand;
use Codeart\Core\Console\ModuleEnableCommand;
use Codeart\Core\Console\ModuleListCommand;
use Codeart\Core\Console\RolesCommand;
use Codeart\Core\Console\SettingsGetCommand;
use Codeart\Core\Console\SettingsListCommand;
use Codeart\Core\Console\SettingsLockCommand;
use Codeart\Core\Console\SettingsSetCommand;
use Codeart\Core\Console\SettingsUnlockCommand;
use Codeart\Core\Console\UserApproveCommand;
use Codeart\Core\Console\UserCreateCommand;
use Codeart\Core\Console\UserDeactivateCommand;
use Codeart\Core\Console\UserInviteCommand;
use Codeart\Core\Console\UserReset2faCommand;
use Codeart\Core\Modules\MenuRegistry;
use Codeart\Core\Modules\ModuleDiscovery;
use Codeart\Core\Modules\ModuleRegistry;
use Codeart\Core\Settings\CoreSettings;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\SettingsManager;
use Codeart\Core\Settings\SettingsRegistry;
use Codeart\Core\Theme\Assets;
use Codeart\Core\Theme\ThemeSettings;
use Codeart\Core\Translations\Locales;
use Illuminate\Console\Scheduling\Schedule;
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

        // Core's definitions are registered when the registry is first built; modules add theirs in their providers.
        $this->app->singleton(SettingsRegistry::class, function (): SettingsRegistry {
            $registry = new SettingsRegistry;
            $registry->register(...CoreSettings::definitions());

            return $registry;
        });
        $this->app->singleton(Settings::class);
        $this->app->singleton(SettingsManager::class);

        $this->app->singleton(Locales::class, fn (): Locales => new Locales(
            $this->app->make(Settings::class),
            (array) config('codeart.locales', []),
        ));

        $this->app->singleton(Assets::class);
        $this->app->singleton(ThemeSettings::class, fn (): ThemeSettings => new ThemeSettings(
            $this->app->make(Settings::class),
            (array) config('codeart.theme_base', []),
            (array) config('codeart.theme_defaults', []),
        ));

        // Last: it reads settings and config registered above.
        $this->app->register(AuthServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'codeart');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'codeart');
        $this->fallbackFrameworkTranslations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Only a super-admin may manage modules (future admin screen). The commands are CLI-only.
        // Super-admin passes every check, including permissions declared by modules.
        Gate::before(fn ($user) => $this->hasSuperAdminRole($user) ? true : null);
        Gate::define('manage-modules', fn ($user): bool => $this->hasSuperAdminRole($user));

        // cron `schedule:run` is the only scheduler on client servers
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('activity-log:prune')->daily());

        // Boot enabled modules once every provider is registered. Registering a provider after
        // the app has booted boots it immediately.
        $this->app->booted(fn () => $this->app->make(ModuleRegistry::class)->bootEnabled());

        if ($this->app->runningInConsole()) {
            $this->commands([ModuleListCommand::class, ModuleEnableCommand::class, ModuleDisableCommand::class, RolesCommand::class,
                SettingsListCommand::class, SettingsGetCommand::class, SettingsSetCommand::class, SettingsLockCommand::class, SettingsUnlockCommand::class,
                UserCreateCommand::class, UserInviteCommand::class, UserApproveCommand::class, UserDeactivateCommand::class, UserReset2faCommand::class,
                ActivityLogPruneCommand::class, MailTestCommand::class, LangTransliterateCommand::class, BrandLoadCommand::class]);

            $this->publishes([
                __DIR__.'/../config/codeart.php' => config_path('codeart.php'),
            ], 'codeart-config');

            // Pre-built assets are shipped inside the package (no Node on servers).
            $this->publishes([
                Assets::packagePath() => public_path('vendor/codeart'),
            ], 'codeart-assets');

            // To change a screen, publish the views and edit them (they then win over core's copy).
            $this->publishes([
                __DIR__.'/../resources/views/auth' => resource_path('views/vendor/codeart/auth'),
                __DIR__.'/../resources/views/components' => resource_path('views/vendor/codeart/components'),
            ], 'codeart-views');
        }
    }

    /**
     * Laravel 13 ships no lang files, so auth.*, passwords.* and validation.* would show as raw keys.
     * When neither the app nor the framework knows a key, use core's copy (an app's own translation always wins).
     */
    private function fallbackFrameworkTranslations(): void
    {
        $this->app->make('translator')->handleMissingKeysUsing(function (string $key, array $replace, ?string $locale) {
            if (preg_match('/^(auth|passwords|validation)\./', $key) !== 1) {
                return null;
            }
            $line = trans('codeart::framework.'.$key, $replace, $locale);

            return $line === 'codeart::framework.'.$key ? null : $line;
        });
    }

    private function hasSuperAdminRole(mixed $user): bool
    {
        return is_object($user) && method_exists($user, 'hasRole') && $user->hasRole('super-admin');
    }
}
