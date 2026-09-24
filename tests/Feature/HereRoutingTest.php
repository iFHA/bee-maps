<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Enums\TravelMode;
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

    public function test_computes_the_route_and_returns_a_typed_dto(): void
    {
        $this->fake();

        $route = $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route(new RouteRequest(
                new Coordinates(50.10228, 8.69821),
                new Coordinates(50.09878, 8.68752),
                includePolyline: true,
                includeLegs: true,
            ));

        $this->assertSame(5200, $route->distance->meters);
        $this->assertSame(780, $route->duration->seconds);
        $this->assertCount(4, $route->polyline->coordinates());

        Http::assertSent(function ($request): bool {
            $url = urldecode($request->url());

            $this->assertStringContainsString('transportMode=car', $url);
            $this->assertStringContainsString('return=summary,polyline', $url);
            $this->assertStringContainsString('apiKey=chave-here-de-teste', $url);

            return true;
        });
    }

    public function test_a_route_without_optimization_is_a_single_upstream_call(): void
    {
        Event::fake([MapRequestCompleted::class]);
        $this->fake();

        $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route(new RouteRequest(new Coordinates(50.10228, 8.69821), new Coordinates(50.09878, 8.68752)));

        Event::assertDispatchedTimes(MapRequestCompleted::class, 1);
        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $event) => $event->service === Service::Routing
                && $event->upstreamCalls === 1,
        );
    }

    private function fakeOptimized(): void
    {
        Http::fake([
            'wps.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/findsequence.json'), true),
                200,
            ),
            'router.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/route.json'), true),
                200,
            ),
        ]);
    }

    private function optimizedRequest(): RouteRequest
    {
        return new RouteRequest(
            new Coordinates(50.10228, 8.69821),
            new Coordinates(50.09878, 8.68752),
            [new Coordinates(50.1001, 8.69), new Coordinates(50.10063, 8.6915)],
            TravelMode::Drive,
            optimizeIntermediates: true,
            includeLegs: true,
        );
    }

    public function test_an_optimized_route_queries_the_sequence_and_then_the_route(): void
    {
        $this->fakeOptimized();

        $route = $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route($this->optimizedRequest());

        $this->assertSame([1, 0], $route->optimizedOrder);
        $this->assertSame(12400, $route->distance->meters);

        // A rota tem que ser pedida na ordem que o findsequence devolveu.
        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'router.hereapi.com')) {
                return false;
            }

            $url = urldecode($request->url());
            $secondPosition = strpos($url, 'via=50.1006300,8.6915000');
            $firstPosition = strpos($url, 'via=50.1001000,8.6900000');

            $this->assertNotFalse($secondPosition);
            $this->assertNotFalse($firstPosition);
            $this->assertLessThan($firstPosition, $secondPosition);

            return true;
        });
    }

    public function test_an_optimized_route_is_one_operation_with_two_upstream_calls(): void
    {
        Event::fake([MapRequestCompleted::class]);
        $this->fakeOptimized();

        $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route($this->optimizedRequest());

        // Um unico evento: sem isso, a POC compararia uma chamada do Google
        // contra duas do HERE e concluiria o oposto do que os dados dizem.
        Event::assertDispatchedTimes(MapRequestCompleted::class, 1);
        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $event) => $event->service === Service::Routing
                && $event->upstreamCalls === 2,
        );
    }

    public function test_optimizing_without_intermediates_does_not_call_findsequence(): void
    {
        // So o router responde: se o findsequence for chamado,
        // preventStrayRequests quebra o teste.
        $this->fake();

        $route = $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route(new RouteRequest(
                new Coordinates(50.10228, 8.69821),
                new Coordinates(50.09878, 8.68752),
                optimizeIntermediates: true,
            ));

        $this->assertSame([], $route->optimizedOrder);
    }
}
