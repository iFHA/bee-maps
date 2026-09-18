<?php

namespace BeeDelivery\BeeMaps\DTOs\Requests;

final readonly class GeocodeFilters
{
    public function __construct(
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?string $country = null,
    ) {
    }
}
