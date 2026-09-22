<?php

namespace BeeDelivery\BeeMaps\Support\Tsp;

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
}
