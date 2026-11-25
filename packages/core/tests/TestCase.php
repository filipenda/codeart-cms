<?php

namespace Codeart\Core\Tests;

use Codeart\Core\CoreServiceProvider;
use Codeart\Core\Modules\ModuleDiscovery;
use Codeart\Core\Tests\Support\FakeDiscovery;
use Codeart\Core\Tests\Support\StubUser;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Fixture modules instead of Composer metadata (core's own tests have no module packages).
        $app->singleton(ModuleDiscovery::class, fn () => new FakeDiscovery);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('cache.default', 'array'); // never share the enabled-modules cache between tests
        $app['config']->set('auth.providers.users.model', StubUser::class);
        $app['config']->set('database.default', 'mariadb');
        $app['config']->set('database.connections.mariadb', [
            'driver' => 'mariadb',
            'host' => getenv('DB_HOST') ?: 'db',
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE') ?: 'test',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'root',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
    }
}
