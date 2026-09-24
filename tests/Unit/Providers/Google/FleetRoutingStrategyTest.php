<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Google;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Providers\Google\Optimization\FleetRoutingStrategy;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\Doubles\TokenFalso;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class FleetRoutingStrategyTest extends TestCase
{
    private const URL = 'https://routeoptimization.googleapis.com/v1/projects/projeto-de-teste:optimizeTours';

    private function strategy(): FleetRoutingStrategy
    {
        return new FleetRoutingStrategy(
            $this->app->make(MapsHttpClient::class),
            new TokenFalso(),
            self::URL,
        );
    }

    private function point(float $offset): Coordinates
    {
        return new Coordinates(-23.5 - $offset, -46.6 - $offset);
    }

    private function request(
        OptimizationObjective $objective = OptimizationObjective::MinDistance,
        bool $withDestination = true,
    ): OptimizeWaypointsRequest {
        return new OptimizeWaypointsRequest(
            origin: $this->point(0),
            destination: $withDestination ? $this->point(0.9) : null,
            intermediates: [$this->point(0.1), $this->point(0.2), $this->point(0.3)],
            objective: $objective,
        );
    }

    private function validResponse(): array
    {
        return [
            'routes' => [[
                'visits' => [
                    ['shipmentIndex' => 2],
                    ['shipmentIndex' => 0],
                    ['shipmentIndex' => 1],
                ],
            ]],
            'metrics' => [
                'aggregatedRouteMetrics' => [
                    'travelDistanceMeters' => 12345,
                    'travelDuration' => '678s',
                ],
            ],
        ];
    }

    public function test_reads_order_and_totals_from_the_response(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response($this->validResponse(), 200)]);

        $result = $this->strategy()->optimize($this->request());

        $this->assertSame([2, 0, 1], $result->order);
        $this->assertSame(12345, $result->distance->meters);
        $this->assertSame(678, $result->duration->seconds);
        $this->assertSame('google.fleet_routing', $result->strategy);
    }

    public function test_the_objective_becomes_cost_in_the_payload(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response($this->validResponse(), 200)]);

        $this->strategy()->optimize($this->request(OptimizationObjective::MinDistance));

        Http::assertSent(function ($request): bool {
            $vehicle = $request->data()['model']['vehicles'][0];

            $this->assertSame(1, $vehicle['costPerKilometer']);
            $this->assertArrayNotHasKey('costPerHour', $vehicle);
            $this->assertSame('Bearer token-de-teste', $request->header('Authorization')[0]);

            return true;
        });
    }

    public function test_the_time_objective_switches_the_cost(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response($this->validResponse(), 200)]);

        $this->strategy()->optimize($this->request(OptimizationObjective::MinTravelTime));

        Http::assertSent(function ($request): bool {
            $vehicle = $request->data()['model']['vehicles'][0];

            $this->assertSame(1, $vehicle['costPerHour']);
            $this->assertArrayNotHasKey('costPerKilometer', $vehicle);

            return true;
        });
    }

    public function test_an_open_tour_omits_endLocation(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response($this->validResponse(), 200)]);

        $this->strategy()->optimize($this->request(withDestination: false));

        Http::assertSent(function ($request): bool {
            $vehicle = $request->data()['model']['vehicles'][0];

            $this->assertArrayHasKey('startLocation', $vehicle);
            $this->assertArrayNotHasKey('endLocation', $vehicle, 'tour aberto termina onde a otimizacao deixar');

            return true;
        });
    }

    public function test_deliveries_go_as_a_list(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response($this->validResponse(), 200)]);

        $this->strategy()->optimize($this->request());

        Http::assertSent(function ($request): bool {
            $shipments = $request->data()['model']['shipments'];

            $this->assertCount(3, $shipments);
            // `deliveries` e campo repeated no proto: mapa no lugar de lista e
            // payload malformado. O legado monta como mapa, e esse caminho nunca
            // foi exercitado em producao.
            $this->assertArrayHasKey(0, $shipments[0]['deliveries']);
            $this->assertArrayHasKey('arrivalLocation', $shipments[0]['deliveries'][0]);

            return true;
        });
    }

    public function test_an_incomplete_order_is_an_error(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response([
            'routes' => [['visits' => [['shipmentIndex' => 0], ['shipmentIndex' => 1]]]],
            'metrics' => ['aggregatedRouteMetrics' => ['travelDistanceMeters' => 1, 'travelDuration' => '1s']],
        ], 200)]);

        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/2 de 3/');

        $this->strategy()->optimize($this->request());
    }

    public function test_a_response_without_a_route_is_an_error(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response(['routes' => []], 200)]);

        $this->expectException(ProviderRequestException::class);

        $this->strategy()->optimize($this->request());
    }

    public function test_a_zeroed_total_is_not_an_error(): void
    {
        // O legado lancava quando a distancia era zero. E guarda por procuracao:
        // o invariante e "a resposta descreve a rota", nao "a distancia e positiva".
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response([
            'routes' => [['visits' => [['shipmentIndex' => 0], ['shipmentIndex' => 1], ['shipmentIndex' => 2]]]],
            'metrics' => ['aggregatedRouteMetrics' => ['travelDuration' => '0s']],
        ], 200)]);

        $result = $this->strategy()->optimize($this->request());

        $this->assertSame(0, $result->distance->meters);
    }
}
