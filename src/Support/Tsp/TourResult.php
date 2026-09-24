<?php

namespace BeeDelivery\BeeMaps\Support\Tsp;

use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;

final readonly class TourResult
{
    /**
     * @param list<int> $order Indices DA MATRIZ, na ordem de visita, sem a origem
     *                         e sem o fim. Quem traduz para indices de
     *                         intermediarios e a estrategia que montou a matriz.
     */
    public function __construct(
        public array $order,
        public Distance $distance,
        public Duration $duration,
    ) {
    }

    /**
     * O que esta rota custa segundo o objetivo pedido. Comparar duas rotas e
     * comparar este numero, e o criterio tem que ser o mesmo que ordenou as
     * pernas — senao a rota "vencedora" ganha por uma medida que ninguem pediu.
     */
    public function cost(OptimizationObjective $objective): int
    {
        return match ($objective) {
            OptimizationObjective::MinDistance => $this->distance->meters,
            OptimizationObjective::MinTravelTime => $this->duration->seconds,
        };
    }
}
