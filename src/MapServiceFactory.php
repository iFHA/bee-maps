<?php

namespace BeeDelivery\BeeMaps;

use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesAutocomplete;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesGeocoding;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesPlaceSearch;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesRouteMatrix;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesRouteOptimization;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesRouting;
use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\Contracts\Services\Geocoding;
use BeeDelivery\BeeMaps\Contracts\Services\PlaceSearch;
use BeeDelivery\BeeMaps\Contracts\Services\RouteMatrix;
use BeeDelivery\BeeMaps\Contracts\Services\RouteOptimization;
use BeeDelivery\BeeMaps\Contracts\Services\Routing;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ServiceNotSupportedByProviderException;
use BeeDelivery\BeeMaps\Providers\ProviderRegistry;

final class MapServiceFactory
{
    public function __construct(private readonly ProviderRegistry $registry)
    {
    }

    public function autocomplete(Provider $provider): Autocomplete
    {
        $mapProvider = $this->registry->get($provider);

        return $mapProvider instanceof ProvidesAutocomplete
            ? $mapProvider->autocomplete()
            : throw ServiceNotSupportedByProviderException::make($provider, Service::Autocomplete);
    }

    public function geocoding(Provider $provider): Geocoding
    {
        $mapProvider = $this->registry->get($provider);

        return $mapProvider instanceof ProvidesGeocoding
            ? $mapProvider->geocoding()
            : throw ServiceNotSupportedByProviderException::make($provider, Service::Geocoding);
    }

    public function placeSearch(Provider $provider): PlaceSearch
    {
        $mapProvider = $this->registry->get($provider);

        return $mapProvider instanceof ProvidesPlaceSearch
            ? $mapProvider->placeSearch()
            : throw ServiceNotSupportedByProviderException::make($provider, Service::PlaceSearch);
    }

    public function routeMatrix(Provider $provider): RouteMatrix
    {
        $mapProvider = $this->registry->get($provider);

        return $mapProvider instanceof ProvidesRouteMatrix
            ? $mapProvider->routeMatrix()
            : throw ServiceNotSupportedByProviderException::make($provider, Service::RouteMatrix);
    }

    public function routeOptimization(Provider $provider): RouteOptimization
    {
        $mapProvider = $this->registry->get($provider);

        return $mapProvider instanceof ProvidesRouteOptimization
            ? $mapProvider->routeOptimization()
            : throw ServiceNotSupportedByProviderException::make($provider, Service::RouteOptimization);
    }

    public function routing(Provider $provider): Routing
    {
        $mapProvider = $this->registry->get($provider);

        return $mapProvider instanceof ProvidesRouting
            ? $mapProvider->routing()
            : throw ServiceNotSupportedByProviderException::make($provider, Service::Routing);
    }
}
