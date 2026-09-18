<?php

namespace BeeDelivery\BeeMaps\Providers\Here;

use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesAutocomplete;
use BeeDelivery\BeeMaps\Contracts\MapProvider;
use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\MissingCredentialsException;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutosuggestRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutosuggestResponseMapper;
use BeeDelivery\BeeMaps\Providers\Here\Services\HereAutocomplete;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class HereProvider implements MapProvider, ProvidesAutocomplete
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

    private function apiKey(): string
    {
        return $this->config['api_key']
            ?? throw MissingCredentialsException::make(Provider::Here, 'bee-maps.here.api_key');
    }
}
