<?php

namespace Codeart\Core;

use Codeart\Core\Admin\Dashboard\QuickAction;
use Codeart\Core\Admin\Dashboard\QuickActions;
use Codeart\Core\Admin\Dashboard\Widget;
use Codeart\Core\Admin\Dashboard\WidgetRegistry;
use Codeart\Core\Admin\Dashboard\Widgets\QuickActionsWidget;
use Codeart\Core\Admin\Dashboard\Widgets\RecentActivityWidget;
use Codeart\Core\Admin\Dashboard\Widgets\SystemStatusWidget;
use Codeart\Core\Admin\Demo\ActivityLogTable;
use Codeart\Core\Admin\Icons;
use Codeart\Core\Admin\Navigation\AdminMenu;
use Codeart\Core\Admin\Navigation\NavItem;
use Codeart\Core\Admin\Search\MenuSearchProvider;
use Codeart\Core\Admin\Search\SearchRegistry;
use Codeart\Core\Auth\AuthServiceProvider;
use Codeart\Core\Auth\UserPolicy;
use Codeart\Core\Console\ActivityLogPruneCommand;
use Codeart\Core\Console\BrandLoadCommand;
use Codeart\Core\Console\LangTransliterateCommand;
use Codeart\Core\Console\MailTestCommand;
use Codeart\Core\Console\MakeModuleCommand;
use Codeart\Core\Console\ModuleDisableCommand;
use Codeart\Core\Console\ModuleEnableCommand;
use Codeart\Core\Console\ModuleListCommand;
use Codeart\Core\Console\PublishingRunCommand;
use Codeart\Core\Console\RolesCommand;
use Codeart\Core\Console\SettingsGetCommand;
use Codeart\Core\Console\SettingsListCommand;
use Codeart\Core\Console\SettingsLockCommand;
use Codeart\Core\Console\SettingsSetCommand;
use Codeart\Core\Console\SettingsUnlockCommand;
use Codeart\Core\Console\TableBenchCommand;
use Codeart\Core\Console\UserApproveCommand;
use Codeart\Core\Console\UserCreateCommand;
use Codeart\Core\Console\UserDeactivateCommand;
use Codeart\Core\Console\UserInviteCommand;
use Codeart\Core\Console\UserReset2faCommand;
use Codeart\Core\Images\ImageProcessor;
use Codeart\Core\Images\InterventionImageProcessor;
use Codeart\Core\Install\InstallServiceProvider;
use Codeart\Core\Modules\MenuRegistry;
use Codeart\Core\Modules\ModuleDiscovery;
use Codeart\Core\Modules\ModuleRegistry;
use Codeart\Core\Publishing\PreviewHeaders;
use Codeart\Core\Publishing\PreviewManager;
use Codeart\Core\Publishing\Publishables;
use Codeart\Core\Settings\CoreSettings;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\SettingsManager;
use Codeart\Core\Settings\SettingsRegistry;
use Codeart\Core\Support\DisplayTime;
use Codeart\Core\Tables\TableRegistry;
use Codeart\Core\Theme\Assets;
use Codeart\Core\Theme\ThemeSettings;
use Codeart\Core\Translations\Locales;
use Codeart\Core\Users\InvitationsTable;
use Codeart\Core\Users\PendingUsersWidget;
use Codeart\Core\Users\UsersTable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
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
        $this->app->singleton(AdminMenu::class);
        $this->app->singleton(WidgetRegistry::class);
        $this->app->singleton(QuickActions::class);
        $this->app->singleton(SearchRegistry::class);
        $this->app->singleton(Icons::class);
        $this->app->singleton(TableRegistry::class);
        $this->app->singleton(DisplayTime::class);
        $this->app->singleton(Publishables::class);
        $this->app->singleton(PreviewManager::class);

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
        $this->app->singleton(ImageProcessor::class, fn (): ImageProcessor => new InterventionImageProcessor(
            (string) config('codeart.images.driver', 'gd'),
            (int) config('codeart.images.trim_alpha_tolerance', 8),
        ));
        $this->app->singleton(ThemeSettings::class, fn (): ThemeSettings => new ThemeSettings(
            $this->app->make(Settings::class),
            (array) config('codeart.theme_base', []),
            (array) config('codeart.theme_defaults', []),
        ));

        // Before auth: the not-installed mode changes the session driver auth would otherwise override.
        $this->app->register(InstallServiceProvider::class);

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
        Gate::before(fn ($user, string $ability, array $arguments = []) => $this->hasSuperAdminRole($user)
            && ! (in_array($ability, UserPolicy::GUARDED, true) && ($arguments[0] ?? null) instanceof Model) ? true : null);
        Gate::define('manage-modules', fn ($user): bool => $this->hasSuperAdminRole($user));

        $this->registerAdminShell();
        $this->app->make(TableRegistry::class)->register(ActivityLogTable::KEY, ActivityLogTable::class);
        $this->app->make(TableRegistry::class)->register(UsersTable::KEY, UsersTable::class);
        $this->app->make(TableRegistry::class)->register(InvitationsTable::KEY, InvitationsTable::class);

        // cron `schedule:run` is the only scheduler on client servers
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('activity-log:prune')->daily());
        // Scheduled publishing and expired preview links: every minute, by the same cron line as everything else.
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('publishing:run')->everyMinute()->withoutOverlapping());
        // Preview responses are never cached or indexed (the middleware only acts on requests that carry a preview token).
        $kernel = $this->app->make(HttpKernel::class);
        $append = [$kernel, 'appendMiddlewareToGroup'];
        if (is_callable($append)) { // the kernel of a real app has it; analysed against Testbench's kernel it looks always true
            $append('web', PreviewHeaders::class);
        }

        // Boot enabled modules once every provider is registered. Registering a provider after
        // the app has booted boots it immediately.
        $this->app->booted(fn () => $this->app->make(ModuleRegistry::class)->bootEnabled());

        if ($this->app->runningInConsole()) {
            $this->commands([ModuleListCommand::class, ModuleEnableCommand::class, ModuleDisableCommand::class, RolesCommand::class,
                SettingsListCommand::class, SettingsGetCommand::class, SettingsSetCommand::class, SettingsLockCommand::class, SettingsUnlockCommand::class,
                UserCreateCommand::class, UserInviteCommand::class, UserApproveCommand::class, UserDeactivateCommand::class, UserReset2faCommand::class,
                ActivityLogPruneCommand::class, MailTestCommand::class, LangTransliterateCommand::class, BrandLoadCommand::class, TableBenchCommand::class, PublishingRunCommand::class, MakeModuleCommand::class]);

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

    /** What core itself puts into the admin: the Dashboard menu item, its widgets, the menu search provider. */
    private function registerAdminShell(): void
    {
        $this->app->make(AdminMenu::class)->add(
            new NavItem('dashboard', 'codeart::admin.menu.dashboard', 'layout-dashboard', 'admin.dashboard', order: 0, module: 'core'),
            new NavItem('profile', 'codeart::profile.title', 'circle-user', 'admin.profile', order: 950, module: 'core', activePatterns: ['admin.profile', 'admin.profile.*']),
            new NavItem('users', 'codeart::users.title', 'users', 'admin.users.index', 'users.view', order: 900, module: 'core', activePatterns: ['admin.users.*']),
        );
        $this->app->make(WidgetRegistry::class)->add(
            new Widget('core.recent-activity', 'codeart::admin.widgets.recent_activity', 'history', 'codeart::admin.widgets.recent-activity', RecentActivityWidget::class, permission: 'users.view', size: 'md', order: 10, module: 'core'),
            new Widget('core.system-status', 'codeart::admin.widgets.system_status', 'activity', 'codeart::admin.widgets.system-status', SystemStatusWidget::class, permission: 'system.status', size: 'sm', order: 20, module: 'core'),
            new Widget('core.pending-users', 'codeart::users.widget.pending', 'user-plus', 'codeart::admin.widgets.pending-users', PendingUsersWidget::class, permission: 'users.approve', size: 'sm', order: 25, module: 'core'),
            new Widget('core.quick-actions', 'codeart::admin.widgets.quick_actions', 'zap', 'codeart::admin.widgets.quick-actions', QuickActionsWidget::class, size: 'sm', order: 30, module: 'core'),
        );
        $this->app->make(QuickActions::class)->add(new QuickAction('users.invite', 'codeart::users.invite.title', 'user-plus', 'admin.users.invite', 'users.create', 5));
        $this->app->make(SearchRegistry::class)->add(MenuSearchProvider::class);
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
