<?php

namespace BeeDelivery\BeeMaps\Support\ValueObjects;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\PlaceReferenceProviderMismatchException;

final readonly class PlaceReference
{
    public function __construct(
        public Provider $provider,
        public string $id,
    ) {
    }

    public function assertBelongsTo(Provider $provider): void
    {
        if ($this->provider !== $provider) {
            throw PlaceReferenceProviderMismatchException::make($provider, $this->provider);
        }
    }
}
