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
    private function ponto(float $offset): Coordinates
    {
        return new Coordinates(-23.5 - $offset, -46.6 - $offset);
    }

    public function test_traduz_indices_da_matriz_para_indices_de_intermediarios(): void
    {
        // Matriz sobre [origem, W0, W1, W2] nas posicoes 0, 300, 100, 200.
        // Guloso a partir de 0: indice 2 (100), indice 3 (200), indice 1 (300).
        // Em indices de intermediarios: 1, 2, 0.
        $matriz = new RouteMatrixFalso([0, 300, 100, 200]);

        $resultado = (new MatrixTspStrategy($this->app->make(MapsHttpClient::class), $matriz, new NearestNeighbourTour()))->optimize(
            new OptimizeWaypointsRequest(
                origin: $this->ponto(0),
                intermediates: [$this->ponto(0.3), $this->ponto(0.1), $this->ponto(0.2)],
                objective: OptimizationObjective::MinDistance,
            ),
        );

        $this->assertSame([1, 2, 0], $resultado->order);
        $this->assertSame('google.matrix_tsp', $resultado->strategy);
        $this->assertSame(OptimizationObjective::MinDistance, $resultado->objective);
        // 0->100->200->300
        $this->assertSame(300, $resultado->distance->meters);
    }

    public function test_tour_aberto_nao_acrescenta_ponto_a_matriz(): void
    {
        $matriz = new RouteMatrixFalso([0, 100, 200]);

        (new MatrixTspStrategy($this->app->make(MapsHttpClient::class), $matriz, new NearestNeighbourTour()))->optimize(
            new OptimizeWaypointsRequest(
                origin: $this->ponto(0),
                intermediates: [$this->ponto(0.1), $this->ponto(0.2)],
                objective: OptimizationObjective::MinDistance,
            ),
        );

        $this->assertCount(3, $matriz->ultimaRequisicao->origins, 'origem + 2 paradas');
        $this->assertSame(
            $matriz->ultimaRequisicao->origins,
            $matriz->ultimaRequisicao->destinations,
            'a matriz e quadrada sobre o mesmo conjunto de pontos',
        );
    }

    public function test_destino_fixo_entra_como_ultimo_ponto_da_matriz(): void
    {
        $matriz = new RouteMatrixFalso([0, 100, 200, 500]);

        $resultado = (new MatrixTspStrategy($this->app->make(MapsHttpClient::class), $matriz, new NearestNeighbourTour()))->optimize(
            new OptimizeWaypointsRequest(
                origin: $this->ponto(0),
                destination: $this->ponto(0.5),
                intermediates: [$this->ponto(0.1), $this->ponto(0.2)],
                objective: OptimizationObjective::MinDistance,
            ),
        );

        $this->assertCount(4, $matriz->ultimaRequisicao->origins);
        $this->assertSame([0, 1], $resultado->order, 'o destino nao aparece na ordem');
        $this->assertSame(500, $resultado->distance->meters, '0->100->200->500');
    }

    public function test_volta_a_origem_soma_a_perna_de_retorno(): void
    {
        // destination = origin: mesmo ponto repetido no fim da lista. O indice do
        // fim nunca e candidato, entao o par de 0 metros entre os dois nao
        // sequestra a primeira escolha — ver §3 do spec.
        $matriz = new RouteMatrixFalso([0, 100, 200, 0]);

        $origem = $this->ponto(0);

        $resultado = (new MatrixTspStrategy($this->app->make(MapsHttpClient::class), $matriz, new NearestNeighbourTour()))->optimize(
            new OptimizeWaypointsRequest(
                origin: $origem,
                destination: $origem,
                intermediates: [$this->ponto(0.1), $this->ponto(0.2)],
                objective: OptimizationObjective::MinDistance,
            ),
        );

        $this->assertSame([0, 1], $resultado->order);
        $this->assertSame(400, $resultado->distance->meters, '0->100->200 mais a volta 200->0');
    }
}
