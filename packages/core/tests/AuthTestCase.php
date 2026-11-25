<?php

namespace Codeart\Core\Tests;

use Codeart\Core\Tests\Support\AdminTestUser;

/** Like TestCase, but with Laravel's own users/sessions migrations and an app User that uses core's trait. */
abstract class AuthTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('auth.providers.users.model', AdminTestUser::class);
        $app['config']->set('mail.default', 'array');
        $app['config']->set('session.driver', 'database');
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('hashing.bcrypt.rounds', 4);
    }

    /** Laravel's own users / password reset / sessions migration, as an app has it (copied from the starter). */
    protected function defineDatabaseMigrations(): void
    {
        $this->app->make('migrator')->path(__DIR__.'/Fixtures/migrations');
    }
}
