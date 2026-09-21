<?php

namespace BeeDelivery\BeeMaps\Support\ValueObjects;

use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;

final readonly class Distance
{
    public function __construct(public int $meters)
    {
        if ($meters < 0) {
            throw new InvalidRequestException("Distancia nao pode ser negativa: {$meters}.");
        }
    }

    public function kilometers(): float
    {
        return $this->meters / 1000;
    }
}
