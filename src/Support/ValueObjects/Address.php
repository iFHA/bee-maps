<?php

namespace BeeDelivery\BeeMaps\Support\ValueObjects;

final readonly class Address
{
    public function __construct(
        public ?string $street,
        public ?string $number,
        public ?string $neighborhood,
        public ?string $city,
        public ?string $state,
        public ?string $country,
        public ?string $postalCode,
        public string $formatted,
    ) {
    }
}
