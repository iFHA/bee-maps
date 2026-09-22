<?php

namespace BeeDelivery\BeeMaps\Providers\Here;

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
use BeeDelivery\BeeMaps\Exceptions\ConfigurationException;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Exceptions\MissingCredentialsException;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutosuggestRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutosuggestResponseMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereDiscoverRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereDiscoverResponseMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereFindSequenceMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereMatrixRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereMatrixResponseMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereRouteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereRouteResponseMapper;
use BeeDelivery\BeeMaps\Providers\Here\Services\HereAutocomplete;
use BeeDelivery\BeeMaps\Providers\Here\Services\HereGeocoding;
use BeeDelivery\BeeMaps\Providers\Here\Services\HerePlaceSearch;
use BeeDelivery\BeeMaps\Providers\Here\Services\HereRouteMatrix;
use BeeDelivery\BeeMaps\Providers\Here\Services\HereRouteOptimization;
use BeeDelivery\BeeMaps\Providers\Here\Services\HereRouting;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\MatrixLimit;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

final class HereProvider implements MapProvider, ProvidesAutocomplete, ProvidesGeocoding, ProvidesPlaceSearch, ProvidesRouteMatrix, ProvidesRouteOptimization, ProvidesRouting
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
            new HereAutosuggestRequestMapper($this->centroDoAutosuggest()),
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
            new HereFindSequenceMapper(),
            $this->config['endpoints']['routing'],
            $this->config['endpoints']['findsequence'],
            $this->apiKey(),
            $this->language,
        );
    }

    public function routeMatrix(): RouteMatrix
    {
        return new HereRouteMatrix(
            $this->http,
            new HereMatrixRequestMapper(),
            new HereMatrixResponseMapper(),
            $this->config['endpoints']['matrix'],
            $this->apiKey(),
            MatrixLimit::normalizar($this->config['matrix_max_elements'] ?? null),
        );
    }

    public function routeOptimization(): RouteOptimization
    {
        return new HereRouteOptimization(
            $this->http,
            new HereFindSequenceMapper(),
            $this->config['endpoints']['findsequence'],
            $this->apiKey(),
        );
    }

    /**
     * Config malformado e erro de configuracao, nao de requisicao: quem precisa
     * agir e quem fez o deploy, e a mensagem tem que dizer qual chave esta errada.
     */
    private function centroDoAutosuggest(): ?Coordinates
    {
        $centro = $this->config['autosuggest_center'] ?? null;

        if ($centro === null || $centro === '') {
            return null;
        }

        try {
            return Coordinates::fromString((string) $centro);
        } catch (InvalidRequestException $e) {
            throw new ConfigurationException(
                'bee-maps.here.autosuggest_center invalido: ' . $e->getMessage(),
            );
        }
    }

    private function apiKey(): string
    {
        return $this->config['api_key']
            ?? throw MissingCredentialsException::make(Provider::Here, 'bee-maps.here.api_key');
    }
}
