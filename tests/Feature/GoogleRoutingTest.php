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

final class GoogleRoutingTest extends TestCase
{
    private function fake(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/route.json'), true),
                200,
            ),
        ]);
    }

    public function test_computes_the_route_and_returns_a_typed_dto(): void
    {
        $this->fake();

        $route = $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Google)
            ->route(new RouteRequest(
                new Coordinates(-23.5, -46.6),
                new Coordinates(-23.6, -46.7),
                [new Coordinates(-23.55, -46.65)],
                TravelMode::TwoWheeler,
                optimizeIntermediates: true,
                includePolyline: true,
                includeLegs: true,
            ));

        $this->assertSame(12400, $route->distance->meters);
        $this->assertSame(1830, $route->duration->seconds);
        $this->assertCount(2, $route->legs);
        $this->assertSame([1, 0], $route->optimizedOrder);

        Http::assertSent(function ($request): bool {
            $this->assertSame('chave-google-de-teste', $request->header('X-Goog-Api-Key')[0]);
            $this->assertStringContainsString('routes.legs', $request->header('X-Goog-FieldMask')[0]);
            $this->assertSame('TWO_WHEELER', $request->data()['travelMode']);
            $this->assertTrue($request->data()['optimizeWaypointOrder']);

            return true;
        });
    }

    public function test_one_google_route_is_one_upstream_call(): void
    {
        Event::fake([MapRequestCompleted::class]);
        $this->fake();

        $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Google)
            ->route(new RouteRequest(new Coordinates(-23.5, -46.6), new Coordinates(-23.6, -46.7)));

        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $event) => $event->service === Service::Routing
                && $event->upstreamCalls === 1,
        );
    }
}
