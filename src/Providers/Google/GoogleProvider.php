<?php

namespace BeeDelivery\BeeMaps\Providers\Google;

use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesAutocomplete;
use BeeDelivery\BeeMaps\Contracts\MapProvider;
use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\MissingCredentialsException;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteResponseMapper;
use BeeDelivery\BeeMaps\Providers\Google\Services\GoogleAutocomplete;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class GoogleProvider implements MapProvider, ProvidesAutocomplete
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly array $config,
        private readonly string $language,
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
        );
    }

    private function apiKey(): string
    {
        return $this->config['key']
            ?? throw MissingCredentialsException::make(Provider::Google, 'bee-maps.google.key');
    }
}
