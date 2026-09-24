<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Http;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderUnavailableException;
use BeeDelivery\BeeMaps\Support\Events\MapRequestCompleted;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

final class MapsHttpClientOperacaoTest extends TestCase
{
    public function test_two_calls_inside_one_operation_become_a_single_event(): void
    {
        Event::fake([MapRequestCompleted::class]);

        Http::fake([
            'primeira.exemplo.test/*' => Http::response(['ok' => 1], 200),
            'segunda.exemplo.test/*' => Http::response(['ok' => 2], 200),
        ]);

        $client = $this->app->make(MapsHttpClient::class);

        $result = $client->operation(Provider::Here, Service::Routing, function () use ($client) {
            $client->get(Provider::Here, Service::Routing, 'https://primeira.exemplo.test/x');

            return $client->get(Provider::Here, Service::Routing, 'https://segunda.exemplo.test/y');
        });

        $this->assertSame(['ok' => 2], $result);

        Event::assertDispatchedTimes(MapRequestCompleted::class, 1);
        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $event) => $event->upstreamCalls === 2
                && $event->httpStatus === 200
                && $event->provider === Provider::Here
                && $event->service === Service::Routing,
        );
    }

    public function test_a_call_outside_an_operation_still_fires_one_event_per_call(): void
    {
        Event::fake([MapRequestCompleted::class]);

        Http::fake(['primeira.exemplo.test/*' => Http::response(['ok' => 1], 200)]);

        $client = $this->app->make(MapsHttpClient::class);
        $client->get(Provider::Here, Service::Routing, 'https://primeira.exemplo.test/x');
        $client->get(Provider::Here, Service::Routing, 'https://primeira.exemplo.test/x');

        Event::assertDispatchedTimes(MapRequestCompleted::class, 2);
        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $event) => $event->upstreamCalls === 1,
        );
    }

    public function test_failing_mid_operation_still_emits_the_event_and_propagates_the_exception(): void
    {
        Event::fake([MapRequestCompleted::class]);

        Http::fake([
            'primeira.exemplo.test/*' => Http::response(['ok' => 1], 200),
            'segunda.exemplo.test/*' => Http::response(['error' => ['message' => 'caiu']], 503),
        ]);

        $client = $this->app->make(MapsHttpClient::class);

        try {
            $client->operation(Provider::Here, Service::Routing, function () use ($client) {
                $client->get(Provider::Here, Service::Routing, 'https://primeira.exemplo.test/x');
                $client->get(Provider::Here, Service::Routing, 'https://segunda.exemplo.test/y');
            });

            $this->fail('Esperava ProviderUnavailableException.');
        } catch (ProviderUnavailableException $e) {
            $this->assertSame(503, $e->httpStatus());
        }

        // Sem isto, uma operacao que falha na segunda chamada sumiria das
        // metricas — exatamente o caso que mais importa medir.
        Event::assertDispatchedTimes(MapRequestCompleted::class, 1);
        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $event) => $event->upstreamCalls === 2 && $event->httpStatus === 503,
        );
    }

    public function test_an_operation_with_no_calls_emits_no_event(): void
    {
        Event::fake([MapRequestCompleted::class]);

        $client = $this->app->make(MapsHttpClient::class);

        $client->operation(Provider::Here, Service::Routing, fn () => 'nada a fazer');

        Event::assertNotDispatched(MapRequestCompleted::class);
    }
}
