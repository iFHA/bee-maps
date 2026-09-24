<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Services;

use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutocompleteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutocompleteResponseMapper;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

/**
 * O /autocomplete do HERE: so endereco e area administrativa, mas funciona sem
 * foco espacial — e o unico caminho para busca nacional. Quem decide se e este
 * ou o /autosuggest e o HereAutocomplete.
 */
final class HereAddressAutocomplete implements Autocomplete
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly HereAutocompleteRequestMapper $requestMapper,
        private readonly HereAutocompleteResponseMapper $responseMapper,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly string $language,
        private readonly string $region,
    ) {
    }

    public function suggest(AutocompleteRequest $request): SuggestionCollection
    {
        $query = $this->requestMapper->toQuery($request, $this->language, $this->region)
            + ['apiKey' => $this->apiKey];

        $response = $this->http->get(Provider::Here, Service::Autocomplete, $this->url, $query);

        return $this->responseMapper->toCollection($response);
    }
}
