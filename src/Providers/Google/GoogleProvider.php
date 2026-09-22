<?php

namespace BeeDelivery\BeeMaps\Providers\Google;

use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesAutocomplete;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesGeocoding;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesPlaceSearch;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesRouteMatrix;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesRouting;
use BeeDelivery\BeeMaps\Contracts\MapProvider;
use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\Contracts\Services\Geocoding;
use BeeDelivery\BeeMaps\Contracts\Services\PlaceSearch;
use BeeDelivery\BeeMaps\Contracts\Services\RouteMatrix;
use BeeDelivery\BeeMaps\Contracts\Services\Routing;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\MissingCredentialsException;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteResponseMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GooglePlaceSearchRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GooglePlaceSearchResponseMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteMatrixRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteMatrixResponseMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteResponseMapper;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleAutocomplete;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleGeocoding;
use BeeDelivery\BeeMaps\Providers\Google\Services\GooglePlaceSearch;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleRouteMatrix;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleRouting;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class GoogleProvider implements MapProvider, ProvidesAutocomplete, ProvidesGeocoding, ProvidesPlaceSearch, ProvidesRouteMatrix, ProvidesRouting
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

    public function routing(): Routing
    {
        return new GoogleRouting(
            $this->http,
            new GoogleRouteRequestMapper(),
            new GoogleRouteResponseMapper(),
            $this->config['endpoints']['routing'],
            $this->apiKey(),
            $this->language,
        );
    }

    public function routeMatrix(): RouteMatrix
    {
        return new GoogleRouteMatrix(
            $this->http,
            new GoogleRouteMatrixRequestMapper(),
            new GoogleRouteMatrixResponseMapper(),
            $this->config['endpoints']['route_matrix'],
            $this->apiKey(),
            $this->limiteDeMatriz(),
        );
    }

    /**
     * Config ausente, vazio ou nao-positivo significa "sem guarda no cliente".
     * Uma variavel declarada sem valor no .env chega como '' — e (int) '' e 0,
     * o que transformaria a guarda em "recuse toda requisicao".
     */
    private function limiteDeMatriz(): ?int
    {
        $limite = $this->config['matrix_max_elements'] ?? null;

        if ($limite === null || $limite === '') {
            return null;
        }

        return (int) $limite > 0 ? (int) $limite : null;
    }

    private function apiKey(): string
    {
        return $this->config['key']
            ?? throw MissingCredentialsException::make(Provider::Google, 'bee-maps.google.key');
    }
}
