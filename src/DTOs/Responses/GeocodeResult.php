<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\Support\ValueObjects\Address;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final readonly class GeocodeResult
{
    /**
     * @param bool       $partial    Resultado e uma aproximacao, nao um casamento exato.
     * @param float|null $matchScore Grau de casamento reportado pelo provider (0 a 1).
     *                               Null no Google, que so devolve o booleano partial_match.
     */
    public function __construct(
        public Address $address,
        public Coordinates $coordinates,
        public bool $partial,
        public ?float $matchScore,
        public ?PlaceReference $place,
    ) {
    }
}
