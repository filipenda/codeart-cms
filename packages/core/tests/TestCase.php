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
}
