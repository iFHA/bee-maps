<?php

namespace BeeDelivery\BeeMaps\Support\Tsp;

use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;

/**
 * 2-opt sobre uma rota ja construida: inverte trechos enquanto isso encurtar.
 *
 * Existe porque o vizinho-mais-proximo nao tem garantia nenhuma de otimalidade —
 * ele decide olhando so a proxima perna e paga a travessia depois. O 2-opt
 * desfaz justamente o cruzamento que essa miopia produz.
 */
final class TwoOptRefinement
{
    /**
     * Teto de passadas, caso duas inversoes fiquem se revezando por empate de
     * arredondamento. So inversao estritamente melhor e aceita, entao a
     * convergencia ja e esperada bem antes disso.
     */
    private const MAX_PASSES = 50;

    public function __construct(private readonly TourMeasure $measure)
    {
    }

    /**
     * @param int   $origin Indice do ponto de partida na matriz.
     * @param ?int  $end    Indice do ponto final, ou null para tour aberto.
     * @param TourResult $tour Rota a refinar, com os totais que ela ja custa.
     *
     * @return TourResult a rota recebida, ou uma estritamente mais curta
     */
    public function refine(
        RouteMatrixEntryCollection $matrix,
        int $origin,
        ?int $end,
        OptimizationObjective $objective,
        TourResult $tour,
    ): TourResult {
        $best = $tour;
        $bestCost = $tour->cost($objective);
        $stops = count($tour->order);
        $passes = 0;
        $improved = true;

        while ($improved && $passes < self::MAX_PASSES) {
            $improved = false;
            $passes++;

            for ($start = 0; $start < $stops - 1; $start++) {
                for ($finish = $start + 1; $finish < $stops; $finish++) {
                    $candidate = $this->measure->measure(
                        $matrix,
                        $origin,
                        $end,
                        $this->reverse($best->order, $start, $finish),
                    );

                    // Inversao que passa por perna sem rota nao tem medida, e
                    // sem medida nao ha como chamar de melhoria.
                    if ($candidate === null) {
                        continue;
                    }

                    if ($candidate->cost($objective) < $bestCost) {
                        $best = $candidate;
                        $bestCost = $candidate->cost($objective);
                        $improved = true;
                    }
                }
            }
        }

        return $best;
    }

    /**
     * @param  list<int> $order
     * @return list<int>
     */
    private function reverse(array $order, int $start, int $finish): array
    {
        $length = $finish - $start + 1;

        array_splice($order, $start, $length, array_reverse(array_slice($order, $start, $length)));

        return $order;
    }
}
