<?php

namespace BeeDelivery\BeeMaps\Tests\Unit;

use BeeDelivery\BeeMaps\Contracts\MapProvider;
use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\ProviderNotSupportedException;
use BeeDelivery\BeeMaps\Exceptions\ServiceNotSupportedByProviderException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Providers\ProviderRegistry;
use BeeDelivery\BeeMaps\Tests\Doubles\ProviderCompleto;
use BeeDelivery\BeeMaps\Tests\Doubles\ProviderSemCapacidades;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class MapServiceFactoryTest extends TestCase
{
    private function factory(MapProvider ...$providers): MapServiceFactory
    {
        return new MapServiceFactory(new ProviderRegistry($providers));
    }

    public function test_devolve_a_implementacao_do_provider_pedido(): void
    {
        $factory = $this->factory(new ProviderCompleto());

        $this->assertInstanceOf(Autocomplete::class, $factory->autocomplete(Provider::Google));
    }

    public function test_provider_nao_registrado_lanca_excecao(): void
    {
        $factory = $this->factory(new ProviderCompleto());

        $this->expectException(ProviderNotSupportedException::class);

        $factory->autocomplete(Provider::Here);
    }

    public function test_provider_sem_a_capacidade_lanca_excecao(): void
    {
        $factory = $this->factory(new ProviderSemCapacidades());

        $this->expectException(ServiceNotSupportedByProviderException::class);

        $factory->autocomplete(Provider::Google);
    }

    public function test_route_optimization_falha_em_provider_sem_a_capacidade(): void
    {
        $this->expectException(ServiceNotSupportedByProviderException::class);

        (new MapServiceFactory(new ProviderRegistry([new ProviderSemCapacidades()])))
            ->routeOptimization(Provider::Google);
    }
}
