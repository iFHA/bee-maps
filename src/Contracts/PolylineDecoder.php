<?php

namespace BeeDelivery\BeeMaps\Contracts;

use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

interface PolylineDecoder
{
    /**
     * @return list<Coordinates>
     *
     * @throws \BeeDelivery\BeeMaps\Exceptions\InvalidRequestException quando a string esta malformada
     */
    public function decode(string $encoded): array;
}
