<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\Place;
use BeeDelivery\BeeMaps\DTOs\Responses\PlaceCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class GooglePlaceSearchResponseMapper
{
    public function __construct(private readonly GoogleAddressMapper $addresses = new GoogleAddressMapper())
    {
    }

    public function toCollection(array $response): PlaceCollection
    {
        $places = [];

        foreach ($response['places'] ?? [] as $place) {
            $places[] = $this->toPlace($place);
        }

        return new PlaceCollection(...$places);
    }

    private function toPlace(array $place): Place
    {
        return new Place(
            place: isset($place['id']) ? new PlaceReference(Provider::Google, $place['id']) : null,
            name: $place['displayName']['text'] ?? '',
            address: $this->addresses->fromComponents(
                $place['addressComponents'] ?? [],
                $place['formattedAddress'] ?? '',
                'longText',
                'shortText',
            ),
            coordinates: new Coordinates(
                (float) $place['location']['latitude'],
                (float) $place['location']['longitude'],
            ),
        );
    }
}
