<?php

namespace BeeDelivery\BeeMaps\Providers\Google;

use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesAutocomplete;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesGeocoding;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesPlaceSearch;
use BeeDelivery\BeeMaps\Contracts\MapProvider;
use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\Contracts\Services\Geocoding;
use BeeDelivery\BeeMaps\Contracts\Services\PlaceSearch;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\MissingCredentialsException;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteResponseMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GooglePlaceSearchRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GooglePlaceSearchResponseMapper;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleAutocomplete;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleGeocoding;
use BeeDelivery\BeeMaps\Providers\Google\Services\GooglePlaceSearch;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class GoogleProvider implements MapProvider, ProvidesAutocomplete, ProvidesGeocoding, ProvidesPlaceSearch
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly array $config,
        private readonly string $language,
        private readonly string $region,
    ) {
    }

    public function slug(): Provider
    {
        return Provider::Google;
    }

    public function autocomplete(): Autocomplete
    {
        return new GoogleAutocomplete(
            $this->http,
            new GoogleAutocompleteRequestMapper(),
            new GoogleAutocompleteResponseMapper(),
            $this->config['endpoints']['autocomplete'],
            $this->apiKey(),
            $this->language,
            $this->region,
        );
    }

    public function geocoding(): Geocoding
    {
        return new GoogleGeocoding(
            $this->http,
            new GoogleGeocodeResponseMapper(),
            $this->config['endpoints']['geocoding'],
            $this->apiKey(),
            $this->language,
        );
    }

    public function placeSearch(): PlaceSearch
    {
        return new GooglePlaceSearch(
            $this->http,
            new GooglePlaceSearchRequestMapper(),
            new GooglePlaceSearchResponseMapper(),
            $this->config['endpoints']['place_search'],
            $this->apiKey(),
            $this->language,
            $this->region,
        );
    }

    private function apiKey(): string
    {
        return $this->config['key']
            ?? throw MissingCredentialsException::make(Provider::Google, 'bee-maps.google.key');
    }
}
