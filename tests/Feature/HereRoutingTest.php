<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\Events\MapRequestCompleted;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

final class HereRoutingTest extends TestCase
{
    private function fake(): void
    {
        Http::fake([
            'router.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/route-uma-secao.json'), true),
                200,
            ),
        ]);
    }

    public function test_calcula_rota_e_devolve_dto_tipado(): void
    {
        $this->fake();

        $rota = $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route(new RouteRequest(
                new Coordinates(50.10228, 8.69821),
                new Coordinates(50.09878, 8.68752),
                includePolyline: true,
                includeLegs: true,
            ));

        $this->assertSame(5200, $rota->distance->meters);
        $this->assertSame(780, $rota->duration->seconds);
        $this->assertCount(4, $rota->polyline->coordinates());

        Http::assertSent(function ($request): bool {
            $url = urldecode($request->url());

            $this->assertStringContainsString('transportMode=car', $url);
            $this->assertStringContainsString('return=summary,polyline', $url);
            $this->assertStringContainsString('apiKey=chave-here-de-teste', $url);

            return true;
        });
    }

    public function test_rota_sem_otimizacao_e_uma_unica_chamada_upstream(): void
    {
        Event::fake([MapRequestCompleted::class]);
        $this->fake();

        $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route(new RouteRequest(new Coordinates(50.10228, 8.69821), new Coordinates(50.09878, 8.68752)));

        Event::assertDispatchedTimes(MapRequestCompleted::class, 1);
        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $evento) => $evento->service === Service::Routing
                && $evento->upstreamCalls === 1,
        );
    }
}
