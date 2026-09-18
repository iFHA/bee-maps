<?php

namespace BeeDelivery\BeeMaps\Support\ValueObjects;

use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;

final readonly class Coordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidRequestException("Latitude fora do intervalo [-90, 90]: {$latitude}.");
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidRequestException("Longitude fora do intervalo [-180, 180]: {$longitude}.");
        }
    }

    public function toString(): string
    {
        return $this->latitude . ',' . $this->longitude;
    }
}
