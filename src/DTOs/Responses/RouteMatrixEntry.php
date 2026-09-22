<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;

final readonly class RouteMatrixEntry
{
    /**
     * @param bool $reachable Falso quando o provider nao encontrou rota entre o par.
     *                        Nesse caso distance e duration vem zerados: sao o
     *                        preenchimento do contrato, nao uma medida.
     */
    public function __construct(
        public int $originIndex,
        public int $destinationIndex,
        public Distance $distance,
        public Duration $duration,
        public bool $reachable,
    ) {
    }
}
