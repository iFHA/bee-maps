<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Services;

use BeeDelivery\BeeMaps\Contracts\Services\PlaceSearch;
use BeeDelivery\BeeMaps\DTOs\Requests\PlaceSearchRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\PlaceCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GooglePlaceSearchRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GooglePlaceSearchResponseMapper;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class GooglePlaceSearch implements PlaceSearch
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly GooglePlaceSearchRequestMapper $requestMapper,
        private readonly GooglePlaceSearchResponseMapper $responseMapper,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly string $language,
        private readonly string $region,
    ) {
    }

    public function search(PlaceSearchRequest $request): PlaceCollection
    {
        $resposta = $this->http->post(
            Provider::Google,
            Service::PlaceSearch,
            $this->url,
            $this->requestMapper->toPayload($request, $this->language, $this->region),
            [
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => $this->requestMapper->fieldMask(),
            ],
        );

        return $this->responseMapper->toCollection($resposta);
    }
}
