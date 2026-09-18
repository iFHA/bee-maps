<?php

namespace BeeDelivery\BeeMaps\Support\Events;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;

final readonly class MapRequestCompleted
{
    public function __construct(
        public Provider $provider,
        public Service $service,
        public int $httpStatus,
        public float $durationMs,
        public int $upstreamCalls = 1,
    ) {
    }
}
