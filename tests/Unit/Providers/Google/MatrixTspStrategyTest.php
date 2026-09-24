<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Google;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Providers\Google\Optimization\MatrixTspStrategy;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\Tsp\NearestNeighbourTour;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\Doubles\RouteMatrixFalso;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class MatrixTspStrategyTest extends TestCase
{
    private function point(float $offset): Coordinates
    {
        return new Coordinates(-23.5 - $offset, -46.6 - $offset);
    }

    public function test_traduz_indices_da_matriz_para_indices_de_intermediarios(): void
    {
        // Matriz sobre [origem, W0, W1, W2] nas posicoes 0, 300, 100, 200.
        // Guloso a partir de 0: indice 2 (100), indice 3 (200), indice 1 (300).
        // Em indices de intermediarios: 1, 2, 0.
        $matrix = new RouteMatrixFalso([0, 300, 100, 200]);

        $result = (new MatrixTspStrategy($this->app->make(MapsHttpClient::class), $matrix, new NearestNeighbourTour()))->optimize(
            new OptimizeWaypointsRequest(
                origin: $this->point(0),
                intermediates: [$this->point(0.3), $this->point(0.1), $this->point(0.2)],
                objective: OptimizationObjective::MinDistance,
            ),
        );

        $this->assertSame([1, 2, 0], $result->order);
        $this->assertSame('google.matrix_tsp', $result->strategy);
        $this->assertSame(OptimizationObjective::MinDistance, $result->objective);
        // 0->100->200->300
        $this->assertSame(300, $result->distance->meters);
    }

    public function test_tour_aberto_nao_acrescenta_ponto_a_matriz(): void
    {
        $matrix = new RouteMatrixFalso([0, 100, 200]);

        (new MatrixTspStrategy($this->app->make(MapsHttpClient::class), $matrix, new NearestNeighbourTour()))->optimize(
            new OptimizeWaypointsRequest(
                origin: $this->point(0),
                intermediates: [$this->point(0.1), $this->point(0.2)],
                objective: OptimizationObjective::MinDistance,
            ),
        );

        $this->assertCount(3, $matrix->lastRequest->origins, 'origem + 2 paradas');
        $this->assertSame(
            $matrix->lastRequest->origins,
            $matrix->lastRequest->destinations,
            'a matriz e quadrada sobre o mesmo conjunto de pontos',
        );
    }

    public function test_destino_fixo_entra_como_ultimo_ponto_da_matriz(): void
    {
        $matrix = new RouteMatrixFalso([0, 100, 200, 500]);

        $result = (new MatrixTspStrategy($this->app->make(MapsHttpClient::class), $matrix, new NearestNeighbourTour()))->optimize(
            new OptimizeWaypointsRequest(
                origin: $this->point(0),
                destination: $this->point(0.5),
                intermediates: [$this->point(0.1), $this->point(0.2)],
                objective: OptimizationObjective::MinDistance,
            ),
        );

        $this->assertCount(4, $matrix->lastRequest->origins);
        $this->assertSame([0, 1], $result->order, 'o destino nao aparece na ordem');
        $this->assertSame(500, $result->distance->meters, '0->100->200->500');
    }

    public function test_volta_a_origem_soma_a_perna_de_retorno(): void
    {
        // destination = origin: mesmo ponto repetido no fim da lista. O indice do
        // fim nunca e candidato, entao o par de 0 metros entre os dois nao
        // sequestra a primeira escolha — ver §3 do spec.
        $matrix = new RouteMatrixFalso([0, 100, 200, 0]);

        $origin = $this->point(0);

        $result = (new MatrixTspStrategy($this->app->make(MapsHttpClient::class), $matrix, new NearestNeighbourTour()))->optimize(
            new OptimizeWaypointsRequest(
                origin: $origin,
                destination: $origin,
                intermediates: [$this->point(0.1), $this->point(0.2)],
                objective: OptimizationObjective::MinDistance,
            ),
        );

        $this->assertSame([0, 1], $result->order);
        $this->assertSame(400, $result->distance->meters, '0->100->200 mais a volta 200->0');
    }
}
