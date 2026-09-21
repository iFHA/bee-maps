<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Services;

use BeeDelivery\BeeMaps\Contracts\Services\Routing;
use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\Route;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereFindSequenceMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereRouteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereRouteResponseMapper;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class HereRouting implements Routing
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly HereRouteRequestMapper $requestMapper,
        private readonly HereRouteResponseMapper $responseMapper,
        private readonly HereFindSequenceMapper $sequenceMapper,
        private readonly string $url,
        private readonly string $findSequenceUrl,
        private readonly string $apiKey,
        private readonly string $language,
    ) {
    }

    public function route(RouteRequest $request): Route
    {
        if (! $request->optimizeIntermediates || $request->intermediates === []) {
            return $this->calcular($request, null);
        }

        // Duas chamadas, um evento: o /v8/routes nao reordena waypoints, e sem
        // o agrupamento a latencia do HERE apareceria dobrada e sem explicacao.
        return $this->http->operacao(Provider::Here, Service::Routing, function () use ($request): Route {
            $sequencia = $this->http->get(
                Provider::Here,
                Service::Routing,
                $this->findSequenceUrl,
                $this->sequenceMapper->toQuery($request, $this->apiKey),
            );

            return $this->calcular($request, $this->sequenceMapper->toOrder($sequencia));
        });
    }

    /**
     * @param list<int>|null $ordemIntermediarios
     */
    private function calcular(RouteRequest $request, ?array $ordemIntermediarios): Route
    {
        $resposta = $this->http->get(
            Provider::Here,
            Service::Routing,
            $this->url,
            $this->requestMapper->toQuery($request, $this->language, $ordemIntermediarios)
                + ['apiKey' => $this->apiKey],
        );

        return $this->responseMapper->toRoute($resposta, $request->includeLegs, $ordemIntermediarios ?? []);
    }
}
