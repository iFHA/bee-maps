<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Services;

use BeeDelivery\BeeMaps\Contracts\Services\Geocoding;
use BeeDelivery\BeeMaps\DTOs\Requests\GeocodeFilters;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResult;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResultCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Support\CountryCode;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class GoogleGeocoding implements Geocoding
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly GoogleGeocodeResponseMapper $mapper,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly string $language,
    ) {
    }

    public function geocode(string $query, ?GeocodeFilters $filters = null): GeocodeResultCollection
    {
        $parameters = ['address' => $query];

        if ($components = $this->components($filters)) {
            $parameters['components'] = $components;
        }

        return $this->fetch($parameters);
    }

    public function reverse(Coordinates $coordinates): GeocodeResultCollection
    {
        return $this->fetch(['latlng' => $coordinates->toString()]);
    }

    public function lookup(PlaceReference $place): ?GeocodeResult
    {
        $place->assertBelongsTo(Provider::Google);

        return $this->fetch(['place_id' => $place->id])->first();
    }

    private function fetch(array $parameters): GeocodeResultCollection
    {
        $response = $this->http->get(
            Provider::Google,
            Service::Geocoding,
            $this->url,
            $parameters + ['language' => $this->language, 'key' => $this->apiKey],
        );

        return $this->mapper->toCollection($response);
    }

    private function components(?GeocodeFilters $filters): ?string
    {
        if ($filters === null) {
            return null;
        }

        $parts = array_filter([
            $filters->city !== null ? 'administrative_area:' . $filters->city : null,
            $filters->postalCode !== null ? 'postal_code:' . $filters->postalCode : null,
            $filters->country !== null ? 'country:' . CountryCode::toAlpha2($filters->country) : null,
        ]);

        return $parts === [] ? null : implode('|', $parts);
    }
}
