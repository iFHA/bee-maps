<?php

namespace BeeDelivery\BeeMaps\Providers\Google;

use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesAutocomplete;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesGeocoding;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesPlaceSearch;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesRouteMatrix;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesRouteOptimization;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesRouting;
use BeeDelivery\BeeMaps\Contracts\MapProvider;
use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\Contracts\Services\Geocoding;
use BeeDelivery\BeeMaps\Contracts\Services\PlaceSearch;
use BeeDelivery\BeeMaps\Contracts\Services\RouteMatrix;
use BeeDelivery\BeeMaps\Contracts\Services\RouteOptimization;
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
use BeeDelivery\BeeMaps\Providers\Google\Optimization\FleetRoutingStrategy;
use BeeDelivery\BeeMaps\Providers\Google\Optimization\MatrixTspStrategy;
use BeeDelivery\BeeMaps\Providers\Google\Optimization\OptimizationStrategy;
use BeeDelivery\BeeMaps\Providers\Google\Optimization\RoutesStrategy;
use BeeDelivery\BeeMaps\Providers\Google\Optimization\ServiceAccountToken;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleAutocomplete;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleGeocoding;
use BeeDelivery\BeeMaps\Providers\Google\Services\GooglePlaceSearch;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleRouteMatrix;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleRouteOptimization;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleRouting;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\MatrixLimit;
use BeeDelivery\BeeMaps\Support\Tsp\NearestNeighbourTour;

final class GoogleProvider implements MapProvider, ProvidesAutocomplete, ProvidesGeocoding, ProvidesPlaceSearch, ProvidesRouteMatrix, ProvidesRouteOptimization, ProvidesRouting
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
            MatrixLimit::normalizar($this->config['matrix_max_elements'] ?? null),
        );
    }

    public function routeOptimization(): RouteOptimization
    {
        return new GoogleRouteOptimization($this->estrategiaPorTempo(), $this->estrategiaPorDistancia());
    }

    private function estrategiaPorTempo(): OptimizationStrategy
    {
        return new RoutesStrategy(
            $this->http,
            new GoogleRouteRequestMapper(),
            $this->config['endpoints']['routing'],
            $this->apiKey(),
            $this->language,
        );
    }

    private function estrategiaPorDistancia(): OptimizationStrategy
    {
        $config = $this->config['route_optimization'] ?? [];

        // Valor desconhecido cai no default: derrubar toda otimizacao por causa
        // de um typo no .env e pior do que usar a estrategia que nao precisa de
        // credencial extra.
        if (($config['min_distance_api'] ?? 'matrix_tsp') !== 'fleet_routing') {
            return new MatrixTspStrategy($this->routeMatrix(), new NearestNeighbourTour());
        }

        $credenciais = $config['service_account'] ?? [];

        return new FleetRoutingStrategy(
            $this->http,
            new ServiceAccountToken($credenciais, $config['scope'] ?? ''),
            str_replace('{projectId}', (string) ($credenciais['project_id'] ?? ''), $config['url'] ?? ''),
        );
    }

    private function apiKey(): string
    {
        return $this->config['key']
            ?? throw MissingCredentialsException::make(Provider::Google, 'bee-maps.google.key');
    }
}
