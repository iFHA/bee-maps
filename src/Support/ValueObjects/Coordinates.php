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
        // sprintf em vez de concatenacao direta: o cast padrao do PHP renderiza
        // 0.0 como "0" e valores muito pequenos em notacao cientifica, formatos
        // que HERE (at=) e Google (latlng=) rejeitam. 7 casas decimais ~ 1cm de
        // precisao. %F (maiusculo) e locale-independent; %f nao e, e um consumidor
        // com locale de virgula decimal geraria um par quebrado.
        return sprintf('%.7F,%.7F', $this->latitude, $this->longitude);
    }
}
