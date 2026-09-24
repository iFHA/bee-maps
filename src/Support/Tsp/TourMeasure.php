<?php

namespace BeeDelivery\BeeMaps\Support\Tsp;

use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;

/**
 * Quanto custa percorrer uma ordem de paradas, segundo a matriz. Separado do
 * NearestNeighbourTour porque quem compara duas ordens — a do guloso e a que o
 * lojista lancou — precisa medir uma ordem que ninguem construiu perna a perna.
 *
 * Ordem que atravessa perna ausente ou sem rota nao tem medida: devolver a soma
 * parcial faria dela a mais barata de todas, que e exatamente o erro que o
 * pacote legado cometia ao tratar medida ausente como zero.
 */
final class TourMeasure
{
    /**
     * @param int       $origin Indice do ponto de partida na matriz.
     * @param ?int      $end    Indice do ponto final, ou null para tour aberto.
     * @param list<int> $order  Paradas na ordem de visita, sem a origem e sem o fim.
     *
     * @return ?TourResult null quando alguma perna da ordem nao existe na matriz
     */
    public function measure(
        RouteMatrixEntryCollection $matrix,
        int $origin,
        ?int $end,
        array $order,
    ): ?TourResult {
        $meters = 0;
        $seconds = 0;
        $current = $origin;

        foreach ([...$order, ...($end === null ? [] : [$end])] as $next) {
            $leg = $matrix->entry($current, $next);

            if ($leg === null || ! $leg->reachable) {
                return null;
            }

            $meters += $leg->distance->meters;
            $seconds += $leg->duration->seconds;
            $current = $next;
        }

        return new TourResult($order, new Distance($meters), new Duration($seconds));
    }
}
