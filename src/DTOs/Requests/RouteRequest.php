<?php

namespace BeeDelivery\BeeMaps\DTOs\Requests;

use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

final readonly class RouteRequest
{
    /**
     * @param list<Coordinates> $intermediates         Waypoints entre origem e destino.
     * @param bool              $optimizeIntermediates Deixa o provider reordenar os
     *                                                 intermediarios. No HERE custa uma
     *                                                 chamada upstream a mais.
     */
    public function __construct(
        public Coordinates $origin,
        public Coordinates $destination,
        public array $intermediates = [],
        public TravelMode $mode = TravelMode::Drive,
        public bool $optimizeIntermediates = false,
        public bool $includePolyline = false,
        public bool $includeLegs = false,
    ) {
    }
}
