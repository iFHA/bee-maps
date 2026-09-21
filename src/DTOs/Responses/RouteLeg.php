<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Support\ValueObjects\Polyline;

final readonly class RouteLeg
{
    public function __construct(
        public Coordinates $origin,
        public Coordinates $destination,
        public Distance $distance,
        public Duration $duration,
        public ?Polyline $polyline,
    ) {
    }
}
