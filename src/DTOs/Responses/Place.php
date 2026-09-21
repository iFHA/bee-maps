<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\Support\ValueObjects\Address;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final readonly class Place
{
    public function __construct(
        public ?PlaceReference $place,
        public string $name,
        public Address $address,
        public Coordinates $coordinates,
    ) {
    }
}
