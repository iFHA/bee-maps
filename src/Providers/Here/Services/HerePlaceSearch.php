<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Services;

use BeeDelivery\BeeMaps\Contracts\Services\PlaceSearch;
use BeeDelivery\BeeMaps\DTOs\Requests\PlaceSearchRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\PlaceCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereDiscoverRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereDiscoverResponseMapper;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class HerePlaceSearch implements PlaceSearch
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly HereDiscoverRequestMapper $requestMapper,
        private readonly HereDiscoverResponseMapper $responseMapper,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly string $language,
        private readonly string $region,
    ) {
    }

    public function search(PlaceSearchRequest $request): PlaceCollection
    {
        $resposta = $this->http->get(
            Provider::Here,
            Service::PlaceSearch,
            $this->url,
            $this->requestMapper->toQuery($request, $this->language, $this->region) + ['apiKey' => $this->apiKey],
        );

        return $this->responseMapper->toCollection($resposta);
    }
}
