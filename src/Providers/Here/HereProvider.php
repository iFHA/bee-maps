<?php

namespace BeeDelivery\BeeMaps\Providers\Here;

use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesAutocomplete;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesGeocoding;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesPlaceSearch;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesRouting;
use BeeDelivery\BeeMaps\Contracts\MapProvider;
use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\Contracts\Services\Geocoding;
use BeeDelivery\BeeMaps\Contracts\Services\PlaceSearch;
use BeeDelivery\BeeMaps\Contracts\Services\Routing;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\MissingCredentialsException;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutosuggestRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutosuggestResponseMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereDiscoverRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereDiscoverResponseMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereRouteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereRouteResponseMapper;
use BeeDelivery\BeeMaps\Providers\Here\Services\HereAutocomplete;
use BeeDelivery\BeeMaps\Providers\Here\Services\HereGeocoding;
use BeeDelivery\BeeMaps\Providers\Here\Services\HerePlaceSearch;
use BeeDelivery\BeeMaps\Providers\Here\Services\HereRouting;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class HereProvider implements MapProvider, ProvidesAutocomplete, ProvidesGeocoding, ProvidesPlaceSearch, ProvidesRouting
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
        return Provider::Here;
    }

    public function autocomplete(): Autocomplete
    {
        return new HereAutocomplete(
            $this->http,
            new HereAutosuggestRequestMapper(),
            new HereAutosuggestResponseMapper(),
            $this->config['endpoints']['autosuggest'],
            $this->apiKey(),
            $this->language,
            $this->region,
        );
    }

    public function geocoding(): Geocoding
    {
        return new HereGeocoding(
            $this->http,
            new HereGeocodeResponseMapper((float) ($this->config['partial_threshold'] ?? 1.0)),
            $this->config['endpoints'],
            $this->apiKey(),
            $this->language,
        );
    }

    public function placeSearch(): PlaceSearch
    {
        return new HerePlaceSearch(
            $this->http,
            new HereDiscoverRequestMapper(),
            new HereDiscoverResponseMapper(),
            $this->config['endpoints']['discover'],
            $this->apiKey(),
            $this->language,
            $this->region,
        );
    }

    public function routing(): Routing
    {
        return new HereRouting(
            $this->http,
            new HereRouteRequestMapper(),
            new HereRouteResponseMapper(),
            $this->config['endpoints']['routing'],
            $this->apiKey(),
            $this->language,
        );
    }

    private function apiKey(): string
    {
        return $this->config['api_key']
            ?? throw MissingCredentialsException::make(Provider::Here, 'bee-maps.here.api_key');
    }
}
