<?php

namespace Codeart\Core\Modules;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Throwable;

/**
 * Knows which modules are installed (Composer), which are enabled (modules table, cached)
 * and boots the enabled ones. A module that is not enabled contributes nothing: no provider,
 * no routes, no views, no migrations path, no menu.
 */
class ModuleRegistry
{
    public const string CACHE_KEY = 'codeart.modules.enabled';

    /** @var array<string, true> */
    private array $booted = [];

    public function __construct(
        private readonly Application $app,
        private readonly ModuleDiscovery $discovery,
    ) {}

    /** @return array<string, InstalledModule> */
    public function installed(): array
    {
        return $this->discovery->discover();
    }

    /** @return array<string, string> package => reason its manifest was rejected */
    public function problems(): array
    {
        return $this->discovery->problems();
    }

    public function find(string $name): ?InstalledModule
    {
        return $this->installed()[$name] ?? null;
    }

    /** @return list<string> names of enabled modules (cached) */
    public function enabledNames(): array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
        } catch (Throwable) {
            $cached = null;
        }
        if (is_array($cached)) {
            return array_values(array_filter($cached, is_string(...)));
        }

        if (! $this->databaseReady()) {
            return []; // install time: nothing is cached so the next request retries
        }

        $names = ModuleRecord::query()->where('enabled', true)->orderBy('name')->pluck('name')->all();
        try {
            Cache::forever(self::CACHE_KEY, $names);
        } catch (Throwable) {
            // cache store not ready either; fall through without caching
        }

        return $names;
    }

    public function isEnabled(string $name): bool
    {
        return in_array($name, $this->enabledNames(), true);
    }

    /** @return list<InstalledModule> enabled AND installed modules */
    public function enabled(): array
    {
        $modules = [];
        foreach ($this->enabledNames() as $name) {
            if (($module = $this->find($name)) !== null) {
                $modules[] = $module;
            }
        }

        return $modules;
    }

    /** @return array<string, ModuleRecord> */
    public function records(): array
    {
        if (! $this->databaseReady()) {
            return [];
        }

        return ModuleRecord::query()->get()->keyBy('name')->all();
    }

    public function forgetCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // nothing cached, nothing to forget
        }
    }

    public function bootEnabled(): void
    {
        foreach ($this->enabled() as $module) {
            $this->boot($module);
        }
    }

    /** Registers provider, routes, views and the migrations path of one module (once). */
    public function boot(InstalledModule $module): void
    {
        $manifest = $module->manifest;
        if (isset($this->booted[$manifest->name])) {
            return;
        }
        $this->booted[$manifest->name] = true;

        if (! class_exists($manifest->provider)) {
            throw new ModuleException("Module \"{$manifest->name}\": provider class {$manifest->provider} was not found (is the package autoloaded?).");
        }
        $this->app->register($manifest->provider);

        $views = $module->path.'/resources/views';
        if (is_dir($views)) {
            View::addNamespace($manifest->name, $views);
        }

        $routes = $module->path.'/routes/web.php';
        if (is_file($routes) && ! $this->app->routesAreCached()) {
            Route::middleware('web')->group($routes);
            // Route names are set after a route is added; without this, route('x') and Route::has('x') miss it.
            Route::getRoutes()->refreshNameLookups();
            Route::getRoutes()->refreshActionLookups();
        }

        if ($manifest->migrations !== null && is_dir($module->path.'/'.$manifest->migrations)) {
            $this->app->make('migrator')->path($module->path.'/'.$manifest->migrations);
        }
    }

    private function databaseReady(): bool
    {
        try {
            return Schema::hasTable('modules');
        } catch (Throwable) {
            return false;
        }
    }
}
