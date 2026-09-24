<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Services;

use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutosuggestRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutosuggestResponseMapper;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

/**
 * O /autosuggest do HERE: devolve POI alem de endereco, ao custo de exigir um
 * foco espacial. Quem decide se e este ou o /autocomplete e o HereAutocomplete.
 */
final class HereAutosuggest implements Autocomplete
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly HereAutosuggestRequestMapper $requestMapper,
        private readonly HereAutosuggestResponseMapper $responseMapper,
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
