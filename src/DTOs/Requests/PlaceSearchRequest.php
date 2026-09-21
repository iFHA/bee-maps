<?php

namespace BeeDelivery\BeeMaps\DTOs\Requests;

use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

final readonly class PlaceSearchRequest
{
    /**
     * @param string|null $region Codigo ISO 3166-1 do pais usado como vies de busca
     *                            quando nao ha coordenada. Null cai no default do config.
     */
    public function __construct(
        public string $query,
        public ?Coordinates $near = null,
        public ?string $region = null,
    ) {
    }
}
