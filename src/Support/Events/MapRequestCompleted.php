<?php

namespace BeeDelivery\BeeMaps\Support\Events;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;

final readonly class MapRequestCompleted
{
    /**
     * @param int $httpStatus Codigo HTTP devolvido pelo provider, ou 0 quando a
     *                        chamada falhou por erro de conexao (timeout, DNS, etc.)
     *                        antes de qualquer resposta chegar.
     */
    public function __construct(
        public Provider $provider,
        public Service $service,
        public int $httpStatus,
        public float $durationMs,
        public int $upstreamCalls = 1,
    ) {
    }
}
