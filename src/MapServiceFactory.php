<?php

namespace BeeDelivery\BeeMaps;

use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesAutocomplete;
use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesGeocoding;
use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\Contracts\Services\Geocoding;
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
}
