<?php

namespace BeeDelivery\BeeMaps\DTOs\Requests;

use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

final readonly class AutocompleteRequest
{
    /**
     * @param list<string> $countries Codigos ISO 3166-1 alpha-2, ex.: ['BR']
     */
    public function __construct(
        public string $query,
        public ?Coordinates $near = null,
        public ?int $radiusMeters = 50000,
        public array $countries = [],
        public ?string $language = null,
    ) {
    }
}
