<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Services;

use BeeDelivery\BeeMaps\Contracts\Services\Routing;
use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\Route;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteResponseMapper;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class GoogleRouting implements Routing
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly GoogleRouteRequestMapper $requestMapper,
        private readonly GoogleRouteResponseMapper $responseMapper,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly string $language,
    ) {
    }

    public function route(RouteRequest $request): Route
    {
        $resposta = $this->http->post(
            Provider::Google,
            Service::Routing,
            $this->url,
            $this->requestMapper->toPayload($request, $this->language),
            [
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => $this->requestMapper->fieldMask($request),
            ],
        );

        return $this->responseMapper->toRoute(
            $resposta,
            $request->includeLegs,
            $request->optimizeIntermediates,
            $request->alternatives > 0,
        );
    }
}
