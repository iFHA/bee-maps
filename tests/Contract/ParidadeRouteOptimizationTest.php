<?php

namespace BeeDelivery\BeeMaps\Tests\Contract;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * O portao de paridade: os dois providers recebem o MESMO request e tem que
 * devolver o mesmo formato de resposta. Os numeros diferem — sao APIs
 * diferentes —, mas a forma nao pode divergir.
 *
 * As fixturas sao assimetricas de proposito: quatro paradas, ordem que nao e a
 * identidade, e medidas que nao se repetem. Com ordem [0,1,2,3] ou pernas
 * iguais, transposicao e off-by-one passariam despercebidos.
 */
final class ParidadeRouteOptimizationTest extends TestCase
{
    public static function providers(): array
    {
        return [
            'google' => [Provider::Google],
            'here' => [Provider::Here],
        ];
    }

    private function fakeBoth(): void
    {
        Http::fake([
            // Google: 4 intermediarios => 5 pernas. Ordem [2, 0, 3, 1].
            'routes.googleapis.com/directions/*' => Http::response([
                'routes' => [[
                    'optimizedIntermediateWaypointIndex' => [2, 0, 3, 1],
                    'legs' => [
                        ['distanceMeters' => 1000, 'duration' => '100s'],
                        ['distanceMeters' => 2000, 'duration' => '200s'],
                        ['distanceMeters' => 3000, 'duration' => '300s'],
                        ['distanceMeters' => 4000, 'duration' => '400s'],
                        ['distanceMeters' => 5000, 'duration' => '500s'],
                    ],
                ]],
            ], 200),

            // HERE: mesma ordem [2, 0, 3, 1], expressa pelos ids que a API ecoa.
            'wps.hereapi.com/*' => Http::response([
                'results' => [[
                    'waypoints' => [
                        ['id' => 'start', 'sequence' => 0],
                        ['id' => 'destination3', 'sequence' => 1],
                        ['id' => 'destination1', 'sequence' => 2],
                        ['id' => 'destination4', 'sequence' => 3],
                        ['id' => 'destination2', 'sequence' => 4],
                        ['id' => 'end', 'sequence' => 5],
                    ],
                    'distance' => '15000',
                    'time' => '1500',
                ]],
            ], 200),
        ]);
    }

    private function request(
        ?Coordinates $destination,
        OptimizationObjective $objective = OptimizationObjective::MinTravelTime,
    ): OptimizeWaypointsRequest {
        return new OptimizeWaypointsRequest(
            origin: new Coordinates(-23.5615, -46.6562),
            destination: $destination,
            intermediates: [
                new Coordinates(-23.5505, -46.6333),
                new Coordinates(-23.587, -46.657),
                new Coordinates(-23.532, -46.639),
                new Coordinates(-23.52, -46.54),
            ],
            objective: $objective,
        );
    }

    #[DataProvider('providers')]
    public function test_a_fixed_end_returns_a_full_permutation_of_the_intermediates(Provider $provider): void
    {
        $this->fakeBoth();

        $result = $this->app->make(MapServiceFactory::class)
            ->routeOptimization($provider)
            ->optimize($this->request(new Coordinates(-23.598, -46.686)));

        $this->assertSame([2, 0, 3, 1], $result->order);
        $this->assertGreaterThan(0, $result->distance->meters);
        $this->assertGreaterThan(0, $result->duration->seconds);
        $this->assertSame(OptimizationObjective::MinTravelTime, $result->objective);
        $this->assertNotSame('', $result->strategy);
    }

    #[DataProvider('providers')]
    public function test_an_open_tour_works_on_both(Provider $provider): void
    {
        $this->fakeBoth();

        $result = $this->app->make(MapServiceFactory::class)
            ->routeOptimization($provider)
            ->optimize($this->request(null));

        $this->assertCount(4, $result->order);
        $this->assertSame([2, 0, 3, 1], $result->order);
    }

    #[DataProvider('providers')]
    public function test_returning_to_origin_works_on_both(Provider $provider): void
    {
        $this->fakeBoth();

        $origin = new Coordinates(-23.5615, -46.6562);

        $result = $this->app->make(MapServiceFactory::class)
            ->routeOptimization($provider)
            ->optimize(new OptimizeWaypointsRequest(
                origin: $origin,
                destination: $origin,
                intermediates: [
                    new Coordinates(-23.5505, -46.6333),
                    new Coordinates(-23.587, -46.657),
                    new Coordinates(-23.532, -46.639),
                    new Coordinates(-23.52, -46.54),
                ],
            ));

        $this->assertCount(4, $result->order);
    }

    #[DataProvider('providers')]
    public function test_the_order_never_includes_origin_or_destination(Provider $provider): void
    {
        $this->fakeBoth();

        $result = $this->app->make(MapServiceFactory::class)
            ->routeOptimization($provider)
            ->optimize($this->request(new Coordinates(-23.598, -46.686)));

        // 4 intermediarios => indices validos sao 0..3. Um 4 aqui significaria
        // que o destino vazou para dentro do resultado da otimizacao.
        foreach ($result->order as $index) {
            $this->assertGreaterThanOrEqual(0, $index);
            $this->assertLessThan(4, $index);
        }
    }

    #[DataProvider('providers')]
    public function test_an_unusable_response_throws_the_same_exception_on_both(Provider $provider): void
    {
        // O portao so cobria o caminho feliz, e era exatamente ai que os dois
        // divergiam: Google lancava ProviderRequestException e HERE
        // InvalidRequestException para o MESMO evento.
        Http::fake([
            'routes.googleapis.com/directions/*' => Http::response(['routes' => []], 200),
            'wps.hereapi.com/*' => Http::response(['results' => []], 200),
        ]);

        try {
            $this->app->make(MapServiceFactory::class)
                ->routeOptimization($provider)
                ->optimize($this->request(new Coordinates(-23.598, -46.686)));

            $this->fail('corpo sem resposta usavel devia ter lancado');
        } catch (ProviderRequestException $e) {
            $this->assertSame($provider, $e->provider());
            $this->assertSame(Service::RouteOptimization, $e->service());
        }
    }

    #[DataProvider('providers')]
    public function test_an_incomplete_order_throws_the_same_exception_on_both(Provider $provider): void
    {
        Http::fake([
            'routes.googleapis.com/directions/*' => Http::response(['routes' => [[
                'optimizedIntermediateWaypointIndex' => [0, 1],
                'legs' => array_fill(0, 5, ['distanceMeters' => 1, 'duration' => '1s']),
            ]]], 200),
            'wps.hereapi.com/*' => Http::response(['results' => [[
                'waypoints' => [
                    ['id' => 'start', 'sequence' => 0],
                    ['id' => 'destination1', 'sequence' => 1],
                    ['id' => 'destination2', 'sequence' => 2],
                    ['id' => 'end', 'sequence' => 3],
                ],
                'distance' => '1000',
                'time' => '100',
            ]]], 200),
        ]);

        $this->expectException(ProviderRequestException::class);

        $this->app->make(MapServiceFactory::class)
            ->routeOptimization($provider)
            ->optimize($this->request(new Coordinates(-23.598, -46.686)));
    }
}
