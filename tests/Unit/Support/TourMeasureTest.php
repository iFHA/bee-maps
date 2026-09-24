<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntry;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Support\Tsp\TourMeasure;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class TourMeasureTest extends TestCase
{
    /**
     * Mesma convencao do NearestNeighbourTourTest: distancia e o modulo da
     * diferenca das posicoes, duracao e a distancia vezes 6.
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

    public function test_it_sums_the_legs_of_an_open_order(): void
    {
        $result = (new TourMeasure())->measure(
            $this->straightLineMatrix([0, 100, 200, 300]),
            origin: 0,
            end: null,
            order: [1, 2, 3],
        );

        $this->assertNotNull($result);
        $this->assertSame([1, 2, 3], $result->order);
        $this->assertSame(300, $result->distance->meters);
        $this->assertSame(1800, $result->duration->seconds);
    }

    public function test_it_sums_the_closing_leg_when_the_tour_has_a_fixed_end(): void
    {
        $result = (new TourMeasure())->measure(
            $this->straightLineMatrix([0, 100, 200, 300]),
            origin: 0,
            end: 3,
            order: [1, 2],
        );

        $this->assertNotNull($result);
        $this->assertSame(300, $result->distance->meters, '0->100->200 mais a perna final 200->300');
    }

    public function test_an_order_with_a_leg_missing_from_the_matrix_has_no_measure(): void
    {
        // Somar zero pela perna ausente inventaria distancia, e devolver a medida
        // parcial faria a ordem furada parecer a mais barata de todas.
        $entries = [
            new RouteMatrixEntry(0, 1, new Distance(100), new Duration(600), true),
            new RouteMatrixEntry(0, 2, new Distance(200), new Duration(1200), true),
            // O par (1, 2) e justamente o que falta.
        ];

        $this->assertNull(
            (new TourMeasure())->measure(new RouteMatrixEntryCollection(...$entries), 0, null, [1, 2]),
        );
    }

    public function test_an_order_crossing_an_unreachable_pair_has_no_measure(): void
    {
        $entries = [
            new RouteMatrixEntry(0, 1, new Distance(100), new Duration(600), true),
            new RouteMatrixEntry(1, 2, new Distance(0), new Duration(0), false),
        ];

        $this->assertNull(
            (new TourMeasure())->measure(new RouteMatrixEntryCollection(...$entries), 0, null, [1, 2]),
        );
    }
}
