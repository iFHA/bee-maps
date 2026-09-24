<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Services;

use BeeDelivery\BeeMaps\Contracts\Services\Geocoding;
use BeeDelivery\BeeMaps\DTOs\Requests\GeocodeFilters;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResult;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResultCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Support\CountryCode;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class HereGeocoding implements Geocoding
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly HereGeocodeResponseMapper $mapper,
        private readonly array $endpoints,
        private readonly string $apiKey,
        private readonly string $language,
    ) {
    }

    public function geocode(string $query, ?GeocodeFilters $filters = null): GeocodeResultCollection
    {
        $parameters = ['q' => $this->withFilters($query, $filters)];

        if ($filters?->country !== null) {
            $parameters['in'] = 'countryCode:' . CountryCode::toAlpha3($filters->country);
        }

        return $this->fetch($this->endpoints['geocode'], $parameters);
    }

    public function reverse(Coordinates $coordinates): GeocodeResultCollection
    {
        return $this->fetch($this->endpoints['revgeocode'], ['at' => $coordinates->toString()]);
    }

    public function lookup(PlaceReference $place): ?GeocodeResult
    {
        $place->assertBelongsTo(Provider::Here);

        return $this->fetch($this->endpoints['lookup'], ['id' => $place->id])->first();
    }

    private function fetch(string $url, array $parameters): GeocodeResultCollection
    {
        $response = $this->http->get(
            Provider::Here,
            Service::Geocoding,
            $url,
            $parameters + ['lang' => $this->language, 'apiKey' => $this->apiKey],
        );

        return $this->mapper->toCollection($response);
    }

    /**
     * O HERE nao tem parametro "components" como o Google: cidade e CEP entram
     * na propria busca por texto livre.
     */
    private function withFilters(string $query, ?GeocodeFilters $filters): string
    {
        $parts = array_filter([$query, $filters?->city, $filters?->postalCode]);

        return implode(', ', $parts);
    }
}
