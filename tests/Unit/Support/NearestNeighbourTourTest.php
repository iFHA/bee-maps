<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntry;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\Tsp\NearestNeighbourTour;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class NearestNeighbourTourTest extends TestCase
{
    /**
     * Matriz completa a partir de posicoes numa reta: distancia em metros e o
     * modulo da diferenca, duracao em segundos e a distancia vezes 6. Assim os
     * dois criterios ordenam igual, e o teste que precisa separa-los monta a
     * matriz na mao.
     *
     * @param list<int|float> $positions
     */
    private function straightLineMatrix(array $positions): RouteMatrixEntryCollection
    {
        $entries = [];

        foreach ($positions as $o => $originPos) {
            foreach ($positions as $d => $destinationPos) {
                $meters = (int) abs($originPos - $destinationPos);

                $entries[] = new RouteMatrixEntry(
                    originIndex: $o,
                    destinationIndex: $d,
                    distance: new Distance($meters),
                    duration: new Duration($meters * 6),
                    reachable: true,
                );
            }
        }

        return new RouteMatrixEntryCollection(...$entries);
    }

    public function test_an_open_tour_sums_no_return_to_nowhere(): void
    {
        // Pontos: 0m, 100m, 200m, 300m. Aberto a partir do indice 0.
        $tour = (new NearestNeighbourTour())->tour(
            $this->straightLineMatrix([0, 100, 200, 300]),
            origin: 0,
            end: null,
            objective: OptimizationObjective::MinDistance,
        );

        $this->assertSame([1, 2, 3], $tour->order);
        $this->assertSame(300, $tour->distance->meters);
        $this->assertSame(1800, $tour->duration->seconds);
    }

    public function test_a_fixed_end_counts_in_the_totals_and_stays_out_of_the_order(): void
    {
        // Pontos: 0m, 100m, 200m, 300m. Fim pinado no indice 3.
        $tour = (new NearestNeighbourTour())->tour(
            $this->straightLineMatrix([0, 100, 200, 300]),
            origin: 0,
            end: 3,
            objective: OptimizationObjective::MinDistance,
        );

        $this->assertSame([1, 2], $tour->order, 'o fim nao e resultado da otimizacao');
        $this->assertSame(300, $tour->distance->meters);
    }

    public function test_returning_to_origin_is_the_end_pointing_at_the_origin_itself(): void
    {
        // destination = origin no contrato vira fim = indice da origem.
        $tour = (new NearestNeighbourTour())->tour(
            $this->straightLineMatrix([0, 100, 200]),
            origin: 0,
            end: 0,
            objective: OptimizationObjective::MinDistance,
        );

        $this->assertSame([1, 2], $tour->order);
        // 0->100->200 e a volta 200->0.
        $this->assertSame(400, $tour->distance->meters);
    }

    public function test_a_duplicated_origin_at_the_end_does_not_hijack_the_first_iteration(): void
    {
        // Caso que o legado so sobrevivia por causa do filtro `distanceMeters <= 0`:
        // o mesmo ponto aparece nos indices 0 e 3, entao o par (0,3) mede 0 metros.
        // Aqui ele nunca e candidato porque 3 e o fim — invariante, nao proxy.
        $tour = (new NearestNeighbourTour())->tour(
            $this->straightLineMatrix([0, 100, 200, 0]),
            origin: 0,
            end: 3,
            objective: OptimizationObjective::MinDistance,
        );

        $this->assertSame([1, 2], $tour->order);
        $this->assertSame(400, $tour->distance->meters);
    }

    public function test_two_stops_at_the_same_coordinate_do_not_disappear(): void
    {
        // Distancia 0 entre dois pontos DISTINTOS e medida valida: duas entregas
        // no mesmo predio existem. O legado descartava o par e a parada sumia.
        $tour = (new NearestNeighbourTour())->tour(
            $this->straightLineMatrix([0, 100, 100, 300]),
            origin: 0,
            end: null,
            objective: OptimizationObjective::MinDistance,
        );

        $this->assertCount(3, $tour->order, 'nenhuma parada pode sumir');
        $this->assertSame([1, 2, 3], $tour->order);
        $this->assertSame(300, $tour->distance->meters);
    }

    public function test_the_objective_picks_the_sorting_criterion(): void
    {
        // Matriz montada a mao: de 0, o indice 1 e mais PERTO e o indice 2 e mais
        // RAPIDO. Os dois objetivos tem que divergir na primeira escolha.
        $entries = [];
        $measurements = [
            // [origem, destino, metros, segundos]
            [0, 1, 100, 900], [0, 2, 500, 100],
            [1, 0, 100, 900], [1, 2, 400, 400],
            [2, 0, 500, 100], [2, 1, 400, 400],
        ];

        foreach ($measurements as [$o, $d, $m, $s]) {
            $entries[] = new RouteMatrixEntry($o, $d, new Distance($m), new Duration($s), true);
        }

        foreach ([0, 1, 2] as $i) {
            $entries[] = new RouteMatrixEntry($i, $i, new Distance(0), new Duration(0), true);
        }

        $matrix = new RouteMatrixEntryCollection(...$entries);
        $tsp = new NearestNeighbourTour();

        $this->assertSame(
            [1, 2],
            $tsp->tour($matrix, 0, null, OptimizationObjective::MinDistance)->order,
        );

        $this->assertSame(
            [2, 1],
            $tsp->tour($matrix, 0, null, OptimizationObjective::MinTravelTime)->order,
        );
    }

    public function test_an_unreachable_pair_is_not_treated_as_just_another_distance(): void
    {
        // Unico caminho de 0 sai para 1, e ele nao existe. Ordem que atravessa
        // trecho sem rota e pior que erro: chega ao entregador como itinerario
        // impossivel.
        $entries = [
            new RouteMatrixEntry(0, 1, new Distance(0), new Duration(0), false),
            new RouteMatrixEntry(1, 0, new Distance(0), new Duration(0), false),
            new RouteMatrixEntry(0, 0, new Distance(0), new Duration(0), true),
            new RouteMatrixEntry(1, 1, new Distance(0), new Duration(0), true),
        ];

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/sem rota|inalcanc/i');

        (new NearestNeighbourTour())->tour(
            new RouteMatrixEntryCollection(...$entries),
            origin: 0,
            end: null,
            objective: OptimizationObjective::MinDistance,
        );
    }

    public function test_a_missing_final_leg_in_the_matrix_is_an_error(): void
    {
        // A matriz nao traz o par ultima-parada -> fim. Fabricar 0 aqui inventaria
        // distancia; a regra do pacote e que medida ausente e erro.
        $entries = [
            new RouteMatrixEntry(0, 1, new Distance(100), new Duration(600), true),
            new RouteMatrixEntry(0, 2, new Distance(200), new Duration(1200), true),
            new RouteMatrixEntry(1, 0, new Distance(100), new Duration(600), true),
            new RouteMatrixEntry(2, 0, new Distance(200), new Duration(1200), true),
            // O par (1, 2) — ultima parada -> fim — e justamente o que falta.
        ];

        $this->expectException(InvalidRequestException::class);

        (new NearestNeighbourTour())->tour(
            new RouteMatrixEntryCollection(...$entries),
            origin: 0,
            end: 2,
            objective: OptimizationObjective::MinDistance,
        );
    }
}
