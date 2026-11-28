<?php

namespace Codeart\Core\Testing;

use Codeart\Core\CoreServiceProvider;
use Codeart\Core\Events\ModuleEnabled;
use Codeart\Core\Modules\ModuleManifest;
use Codeart\Core\Permissions\CreateDefaultRoles;
use Codeart\Core\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use Spatie\Permission\Models\Permission;

/**
 * Base class of the tests that `make:module` generates (and of any module's own tests).
 *
 * Boots core plus ONE module the way the module registry does (provider, `routes/web.php` under the `web` group, views,
 * migrations), on a MariaDB test database (`DB_DATABASE`, default `test`; never the dev database), with the default
 * roles, the module's permissions and its default grants, and two-factor switched off. Every test runs in a transaction.
 */
abstract class ModuleTestCase extends Orchestra
{
    use RefreshDatabase;

    /** Absolute path of the module (the folder with module.json). */
    abstract protected function modulePath(): string;

    public function manifest(): ModuleManifest
    {
        return ModuleManifest::fromFile($this->modulePath().'/module.json');
    }

    protected function getPackageProviders($app): array
    {
        $provider = $this->manifest()->provider;

        return [CoreServiceProvider::class, $provider];
    }

    protected function defineEnvironment($app): void
    {
        $name = (string) (getenv('DB_DATABASE') ?: 'test');
        $token = getenv('TEST_TOKEN') ?: null;
        $database = $token ? $name.'_'.$token : $name;
        $this->createDatabase($database);

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('hashing.bcrypt.rounds', 4);
        $app['config']->set('auth.providers.users.model', StaffTestUser::class);
        $app['config']->set('view.compiled', sys_get_temp_dir().'/codeart-module-views-'.$database);
        $app['config']->set('database.default', 'mariadb');
        $app['config']->set('database.connections.mariadb', [
            'driver' => 'mariadb',
            'host' => getenv('DB_HOST') ?: 'db',
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => $database,
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'root',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        is_dir($app['config']->get('view.compiled')) || mkdir($app['config']->get('view.compiled'), 0777, true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/migrations'); // the framework's users table, as an app has it
        $manifest = $this->manifest();
        if ($manifest->migrations !== null) {
            $this->loadMigrationsFrom($this->modulePath().'/'.$manifest->migrations);
        }
    }

    protected function defineRoutes($router): void
    {
        View::addNamespace($this->manifest()->name, $this->modulePath().'/resources/views');
        if (is_file($routes = $this->modulePath().'/routes/web.php')) {
            Route::middleware('web')->group($routes);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        app(CreateDefaultRoles::class)();
        app(Settings::class)->flush();
        app(Settings::class)->set('security.2fa.mode', 'off');
        $this->enableModule();
    }

    protected function tearDown(): void
    {
        if ($this->app !== null) {
            $this->app['db']->disconnect();
            $this->app['db']->purge();
        }

        parent::tearDown();
    }

    /** What enabling does after the module is booted: permissions, then the `ModuleEnabled` event (default grants). */
    protected function enableModule(): void
    {
        $manifest = $this->manifest();
        foreach ($manifest->permissions as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }
        event(new ModuleEnabled($manifest, 'dev-test'));
    }

    /** A signed-in-able staff user with a role (password `Secret-pass-123`). */
    public function staff(string $role = 'editor'): StaffTestUser
    {
        $user = StaffTestUser::create([
            'name' => ucfirst($role),
            'email' => $role.'-'.uniqid().'@example.com',
            'password' => 'Secret-pass-123',
            'email_verified_at' => now(),
            'approved_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user->fresh() ?? $user;
    }

    private function createDatabase(string $database): void
    {
        static $done = [];
        if (isset($done[$database])) {
            return;
        }
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s', getenv('DB_HOST') ?: 'db', getenv('DB_PORT') ?: '3306'),
            getenv('DB_USERNAME') ?: 'root',
            getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'root',
        );
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $done[$database] = true;
    }
}
