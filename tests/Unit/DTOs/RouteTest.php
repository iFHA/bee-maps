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
    public function test_request_tem_defaults_conservadores(): void
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

    public function test_rota_sem_pernas_nem_otimizacao_tem_colecoes_vazias(): void
    {
        $rota = new Route(new Distance(1200), new Duration(300), null);

        $this->assertSame(1.2, $rota->distance->kilometers());
        $this->assertSame(5.0, $rota->duration->minutes());
        $this->assertNull($rota->polyline);
        $this->assertSame([], $rota->legs);
        $this->assertSame([], $rota->optimizedOrder);
    }

    public function test_rota_carrega_pernas_e_ordem_otimizada(): void
    {
        $perna = new RouteLeg(
            origin: new Coordinates(-23.5, -46.6),
            destination: new Coordinates(-23.6, -46.7),
            distance: new Distance(1200),
            duration: new Duration(300),
            polyline: null,
        );

        $rota = new Route(new Distance(1200), new Duration(300), null, [$perna], [1, 0]);

        $this->assertCount(1, $rota->legs);
        $this->assertSame($perna, $rota->legs[0]);
        $this->assertSame([1, 0], $rota->optimizedOrder);
    }
}
