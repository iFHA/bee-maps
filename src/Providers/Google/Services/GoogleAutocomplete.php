<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Services;

use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteResponseMapper;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class GoogleAutocomplete implements Autocomplete
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly GoogleAutocompleteRequestMapper $requestMapper,
        private readonly GoogleAutocompleteResponseMapper $responseMapper,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly string $language,
        private readonly string $region,
    ) {
    }

    public function suggest(AutocompleteRequest $request): SuggestionCollection
    {
        $response = $this->http->post(
            Provider::Google,
            Service::Autocomplete,
            $this->url,
            $this->requestMapper->toPayload($request, $this->language, $this->region),
            [
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => $this->requestMapper->fieldMask(),
            ],
        );

        return $this->responseMapper->toCollection($response);
    }
}
