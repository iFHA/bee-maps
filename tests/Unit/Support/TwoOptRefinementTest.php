<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntry;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Support\Tsp\TourMeasure;
use BeeDelivery\BeeMaps\Support\Tsp\TourResult;
use BeeDelivery\BeeMaps\Support\Tsp\TwoOptRefinement;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class TwoOptRefinementTest extends TestCase
{
    /** @param list<int|float> $positions */
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

    private function refinement(): TwoOptRefinement
    {
        return new TwoOptRefinement(new TourMeasure());
    }

    public function test_it_reverses_the_segment_that_shortens_the_route(): void
    {
        // Paradas em +2km, +3km, -1km, -4km. O guloso sai para a mais perta (-1km)
        // e paga a travessia depois: 12km. Invertendo o trecho das tres primeiras
        // paradas a rota cai para 10km, que e o otimo.
        $result = $this->refinement()->refine(
            $this->straightLineMatrix([0, 2000, 3000, -1000, -4000]),
            origin: 0,
            end: null,
            objective: OptimizationObjective::MinDistance,
            tour: new TourResult([3, 1, 2, 4], new Distance(12000), new Duration(72000)),
        );

        $this->assertSame([2, 1, 3, 4], $result->order);
        $this->assertSame(10000, $result->distance->meters);
        $this->assertSame(60000, $result->duration->seconds, 'os totais acompanham a ordem nova');
    }

    public function test_it_keeps_the_route_when_no_reversal_helps(): void
    {
        // Empate tambem mantem: so trecho estritamente mais curto e aceito.
        $result = $this->refinement()->refine(
            $this->straightLineMatrix([0, 100, 200, 300]),
            origin: 0,
            end: null,
            objective: OptimizationObjective::MinDistance,
            tour: new TourResult([1, 2, 3], new Distance(300), new Duration(1800)),
        );

        $this->assertSame([1, 2, 3], $result->order);
        $this->assertSame(300, $result->distance->meters);
    }

    public function test_the_objective_picks_what_the_reversal_has_to_shorten(): void
    {
        // Matriz montada a mao: inverter as duas ultimas paradas piora a distancia
        // (30 -> 70) e melhora muito o tempo (120 -> 30). Os dois objetivos tem
        // que divergir.
        $measurements = [
            // [origem, destino, metros, segundos]
            [0, 1, 10, 10], [0, 2, 100, 100], [0, 3, 100, 100],
            [1, 2, 10, 100], [1, 3, 50, 10], [2, 3, 10, 10],
        ];

        $entries = [];
        foreach ($measurements as [$o, $d, $m, $s]) {
            $entries[] = new RouteMatrixEntry($o, $d, new Distance($m), new Duration($s), true);
            $entries[] = new RouteMatrixEntry($d, $o, new Distance($m), new Duration($s), true);
        }

        $matrix = new RouteMatrixEntryCollection(...$entries);
        $tour = new TourResult([1, 2, 3], new Distance(30), new Duration(120));

        $this->assertSame(
            [1, 2, 3],
            $this->refinement()->refine($matrix, 0, null, OptimizationObjective::MinDistance, $tour)->order,
        );

        $this->assertSame(
            [1, 3, 2],
            $this->refinement()->refine($matrix, 0, null, OptimizationObjective::MinTravelTime, $tour)->order,
        );
    }

    public function test_a_reversal_it_cannot_measure_is_not_an_improvement(): void
    {
        // A volta pelo par sem rota mede zero em metros. Sem recusar o que nao da
        // para medir, o 2-opt escolheria justamente o trecho impossivel.
        $entries = [
            new RouteMatrixEntry(0, 1, new Distance(100), new Duration(600), true),
            new RouteMatrixEntry(0, 2, new Distance(0), new Duration(0), false),
            new RouteMatrixEntry(1, 2, new Distance(100), new Duration(600), true),
            new RouteMatrixEntry(2, 1, new Distance(100), new Duration(600), true),
        ];

        $result = $this->refinement()->refine(
            new RouteMatrixEntryCollection(...$entries),
            origin: 0,
            end: null,
            objective: OptimizationObjective::MinDistance,
            tour: new TourResult([1, 2], new Distance(200), new Duration(1200)),
        );

        $this->assertSame([1, 2], $result->order);
        $this->assertSame(200, $result->distance->meters);
    }
}
