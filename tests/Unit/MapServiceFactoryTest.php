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
    private function fabrica(MapProvider ...$providers): MapServiceFactory
    {
        return new MapServiceFactory(new ProviderRegistry($providers));
    }

    public function test_devolve_a_implementacao_do_provider_pedido(): void
    {
        $fabrica = $this->fabrica(new ProviderCompleto());

        $this->assertInstanceOf(Autocomplete::class, $fabrica->autocomplete(Provider::Google));
    }

    public function test_provider_nao_registrado_lanca_excecao(): void
    {
        $fabrica = $this->fabrica(new ProviderCompleto());

        $this->expectException(ProviderNotSupportedException::class);

        $fabrica->autocomplete(Provider::Here);
    }

    public function test_provider_sem_a_capacidade_lanca_excecao(): void
    {
        $fabrica = $this->fabrica(new ProviderSemCapacidades());

        $this->expectException(ServiceNotSupportedByProviderException::class);

        $fabrica->autocomplete(Provider::Google);
    }
}
