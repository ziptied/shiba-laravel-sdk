<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Tests;

use Bentonow\ShibaLaravel\ShibaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('shiba.team_id', 3);
        $app['config']->set('shiba.cluster_id', 3);
    }

    protected function getPackageProviders($app): array
    {
        return [ShibaServiceProvider::class];
    }
}
