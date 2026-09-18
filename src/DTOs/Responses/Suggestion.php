<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final readonly class Suggestion
{
    public function __construct(
        public ?PlaceReference $place,
        public string $description,
        public string $mainText,
        public string $secondaryText,
        public bool $isEstablishment,
    ) {
    }
}
