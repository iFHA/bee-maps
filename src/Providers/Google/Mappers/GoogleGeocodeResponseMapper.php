<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResult;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResultCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class GoogleGeocodeResponseMapper
{
    public function __construct(private readonly GoogleAddressMapper $addresses = new GoogleAddressMapper())
    {
    }

    public function toCollection(array $response): GeocodeResultCollection
    {
        $results = [];

        foreach ($response['results'] ?? [] as $result) {
            $results[] = $this->toResult($result);
        }

        return new GeocodeResultCollection(...$results);
    }

    private function toResult(array $result): GeocodeResult
    {
        return new GeocodeResult(
            address: $this->addresses->fromComponents(
                $result['address_components'] ?? [],
                $result['formatted_address'] ?? '',
                'long_name',
                'short_name',
            ),
            coordinates: new Coordinates(
                (float) $result['geometry']['location']['lat'],
                (float) $result['geometry']['location']['lng'],
            ),
            partial: ($result['partial_match'] ?? false) === true,
            // O Google nao expoe grau de casamento, so o booleano acima.
            matchScore: null,
            place: isset($result['place_id'])
                ? new PlaceReference(Provider::Google, $result['place_id'])
                : null,
        );
    }
}
