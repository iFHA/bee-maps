<?php

namespace BeeDelivery\BeeMaps\Tests;

use BeeDelivery\BeeMaps\BeeMapsServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Qualquer requisição não coberta por Http::fake() deve falhar em vez de
        // sair para a rede real: sem isso, um typo no padrão de URL de um fake
        // faria o teste bater na API real do Google/HERE com a chave do ambiente.
        Http::preventStrayRequests();
    }

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
