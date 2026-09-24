<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\DTOs;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\Route;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteLeg;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class RouteTest extends TestCase
{
    public function test_the_request_has_conservative_defaults(): void
    {
        $request = new RouteRequest(new Coordinates(-23.5, -46.6), new Coordinates(-23.6, -46.7));

        $this->assertSame([], $request->intermediates);
        $this->assertSame(TravelMode::Drive, $request->mode);
        $this->assertFalse($request->optimizeIntermediates);
        // Polyline e legs sao opt-in: pedir geometria encarece o field mask do
        // Google e infla a resposta do HERE sem que o consumidor tenha pedido.
        $this->assertFalse($request->includePolyline);
        $this->assertFalse($request->includeLegs);
    }

    public function test_a_route_without_legs_or_optimization_has_empty_collections(): void
    {
        $route = new Route(new Distance(1200), new Duration(300), null);

        $this->assertSame(1.2, $route->distance->kilometers());
        $this->assertSame(5.0, $route->duration->minutes());
        $this->assertNull($route->polyline);
        $this->assertSame([], $route->legs);
        $this->assertSame([], $route->optimizedOrder);
    }

    public function test_the_route_carries_legs_and_the_optimized_order(): void
    {
        $leg = new RouteLeg(
            origin: new Coordinates(-23.5, -46.6),
            destination: new Coordinates(-23.6, -46.7),
            distance: new Distance(1200),
            duration: new Duration(300),
            polyline: null,
        );

        $route = new Route(new Distance(1200), new Duration(300), null, [$leg], [1, 0]);

        $this->assertCount(1, $route->legs);
        $this->assertSame($leg, $route->legs[0]);
        $this->assertSame([1, 0], $route->optimizedOrder);
    }
}
