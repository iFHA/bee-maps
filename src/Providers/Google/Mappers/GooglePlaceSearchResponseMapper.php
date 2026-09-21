<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\Place;
use BeeDelivery\BeeMaps\DTOs\Responses\PlaceCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class GooglePlaceSearchResponseMapper
{
    public function __construct(private readonly GoogleAddressMapper $enderecos = new GoogleAddressMapper())
    {
    }

    public function toCollection(array $resposta): PlaceCollection
    {
        $lugares = [];

        foreach ($resposta['places'] ?? [] as $lugar) {
            $lugares[] = $this->toPlace($lugar);
        }

        return new PlaceCollection(...$lugares);
    }

    private function toPlace(array $lugar): Place
    {
        return new Place(
            place: isset($lugar['id']) ? new PlaceReference(Provider::Google, $lugar['id']) : null,
            name: $lugar['displayName']['text'] ?? '',
            address: $this->enderecos->fromComponents(
                $lugar['addressComponents'] ?? [],
                $lugar['formattedAddress'] ?? '',
                'longText',
                'shortText',
            ),
            coordinates: new Coordinates(
                (float) $lugar['location']['latitude'],
                (float) $lugar['location']['longitude'],
            ),
        );
    }
}
