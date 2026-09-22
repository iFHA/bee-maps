<?php

namespace BeeDelivery\BeeMaps\DTOs\Requests;

use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

final readonly class RouteMatrixRequest
{
    /**
     * @param list<Coordinates> $origins
     * @param list<Coordinates> $destinations
     */
    public function __construct(
        public array $origins,
        public array $destinations,
        public TravelMode $mode = TravelMode::Drive,
    ) {
        if ($origins === [] || $destinations === []) {
            throw new InvalidRequestException(
                'Uma matriz de rotas precisa de ao menos uma origem e um destino.',
            );
        }
    }

    /**
     * Numero de pares origem-destino. E esta conta, e nao o numero de pontos,
     * que os dois providers limitam.
     */
    public function elements(): int
    {
        return count($this->origins) * count($this->destinations);
    }
}
