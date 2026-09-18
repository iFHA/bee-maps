<?php

namespace BeeDelivery\BeeMaps\Providers;

use BeeDelivery\BeeMaps\Contracts\MapProvider;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\ProviderNotSupportedException;

final class ProviderRegistry
{
    /** @var array<string, MapProvider> */
    private array $providers = [];

    /** @param list<MapProvider> $providers */
    public function __construct(array $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->slug()->value] = $provider;
        }
    }

    public function get(Provider $provider): MapProvider
    {
        return $this->providers[$provider->value]
            ?? throw ProviderNotSupportedException::make($provider);
    }
}
