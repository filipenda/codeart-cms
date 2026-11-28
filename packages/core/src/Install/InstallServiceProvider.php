<?php

namespace Codeart\Core\Install;

use Codeart\Core\Console\InstallCommand;
use Codeart\Core\Install\Http\RedirectToInstaller;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * The installer: wizard, CLI command and the "not installed yet" mode. Switched on with codeart.install.enabled
 * (client packages: yes; the dev app and core's tests: no).
 */
class InstallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InstallPaths::class, fn () => InstallPaths::fromConfig());
        $this->app->bind(InstallState::class);
        $this->app->bind(Probe::class);

        $this->prepareUninstalledBoot();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }
        if (! InstallState::enabled()) {
            return;
        }
        $this->prepareUninstalledBoot(); // again: an app (or a test) may switch the installer on after registration

        $this->app->make(Kernel::class)->pushMiddleware(RedirectToInstaller::class);
        if (! $this->app->routesAreCached()) {
            Route::group([], __DIR__.'/../../routes/install.php');
        }
    }

    /**
     * Before the first install there is no .env: no APP_KEY, no database. Use files for sessions and cache, a key kept in storage
     * (it becomes the real APP_KEY, so the wizard session survives writing .env), and encrypted sessions (they hold the passwords).
     */
    private function prepareUninstalledBoot(): void
    {
        if (! InstallState::enabled() || $this->app->make(InstallState::class)->installed() || $this->app->make('config')->get('codeart.install.pending')) {
            return;
        }
        $config = $this->app->make('config');
        $config->set('codeart.install.pending', true);
        $config->set('session.driver', 'file');
        $config->set('session.encrypt', true);
        $config->set('cache.default', 'file');
        $config->set('queue.default', 'sync');
        $config->set('app.debug', false);

        if ((string) $config->get('app.key') === '') {
            $file = InstallPaths::fromConfig()->storage().'/app/installer.key';
            if (! is_file($file) && is_dir(dirname($file))) {
                file_put_contents($file, 'base64:'.base64_encode(random_bytes(32)));
                chmod($file, 0600);
            }
            if (is_file($file)) {
                $config->set('app.key', trim((string) file_get_contents($file)));
            }
        }
    }
}
