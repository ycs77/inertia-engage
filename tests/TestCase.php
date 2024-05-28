<?php

namespace Inertia\Engage\Tests;

use Inertia\EngageServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // use WithWorkbench;

    protected function defineEnvironment($app)
    {
        $app['view']->addLocation(__DIR__.'/stubs/views');
    }

    protected function getPackageProviders($app)
    {
        return [
            EngageServiceProvider::class,
        ];
    }
}
