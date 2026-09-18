<?php

namespace BeeDelivery\BeeMaps\Tests;

use BeeDelivery\BeeMaps\BeeMapsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [BeeMapsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('bee-maps.google.key', 'chave-google-de-teste');
        $app['config']->set('bee-maps.here.api_key', 'chave-here-de-teste');
    }
}
