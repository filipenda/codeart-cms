<?php

namespace Codeart\Core;

use Codeart\Core\Theme\ThemeSettings;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/codeart.php', 'codeart');

        $this->app->singleton(ThemeSettings::class, fn (): ThemeSettings => new ThemeSettings(
            (array) config('codeart.theme', []),
            (array) config('codeart.theme_base', []),
            (array) config('codeart.theme_defaults', []),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'codeart');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/codeart.php' => config_path('codeart.php'),
            ], 'codeart-config');

            // Pre-built assets are shipped inside the package (no Node on servers).
            $this->publishes([
                __DIR__.'/../resources/dist' => public_path('vendor/codeart'),
            ], 'codeart-assets');
        }
    }
}
