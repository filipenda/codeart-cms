<?php

namespace Codeart\Core\Tests;

use Codeart\Core\CoreServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
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
