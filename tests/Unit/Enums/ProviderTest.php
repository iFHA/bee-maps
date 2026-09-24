<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Enums;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class ProviderTest extends TestCase
{
    public function test_the_provider_is_created_from_the_slug(): void
    {
        $this->assertSame(Provider::Here, Provider::from('here'));
        $this->assertSame('google', Provider::Google->value);
    }

    public function test_service_uses_snake_case_as_its_value(): void
    {
        $this->assertSame('route_optimization', Service::RouteOptimization->value);
        $this->assertCount(6, Service::cases());
    }
}
