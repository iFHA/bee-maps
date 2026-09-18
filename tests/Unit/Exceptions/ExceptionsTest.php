<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Exceptions;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\BeeMapsException;
use BeeDelivery\BeeMaps\Exceptions\ProviderRateLimitException;
use BeeDelivery\BeeMaps\Exceptions\ServiceNotSupportedByProviderException;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class ExceptionsTest extends TestCase
{
    public function test_toda_excecao_do_pacote_descende_da_base(): void
    {
        $e = ServiceNotSupportedByProviderException::make(Provider::Here, Service::RouteOptimization);

        $this->assertInstanceOf(BeeMapsException::class, $e);
        $this->assertStringContainsString('here', $e->getMessage());
        $this->assertStringContainsString('route_optimization', $e->getMessage());
    }

    public function test_erro_de_provider_carrega_contexto_para_o_log(): void
    {
        $e = new ProviderRateLimitException(Provider::Google, Service::Geocoding, 'quota estourada', 429, 'OVER_QUERY_LIMIT');

        $this->assertSame(Provider::Google, $e->provider());
        $this->assertSame(Service::Geocoding, $e->service());
        $this->assertSame(429, $e->httpStatus());
        $this->assertSame('OVER_QUERY_LIMIT', $e->providerCode());
    }
}
