<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Services;

use BeeDelivery\BeeMaps\Contracts\Services\RouteOptimization;
use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\OptimizedWaypoints;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereFindSequenceMapper;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;

/**
 * Waypoints Sequence API. Diferente do HereRouting, NAO chama /v8/routes depois:
 * este contrato pede ordem e totais, e o findsequence2 devolve os dois. Uma
 * chamada upstream, nao duas.
 */
final class HereRouteOptimization implements RouteOptimization
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly HereFindSequenceMapper $mapper,
        private readonly string $url,
        private readonly string $apiKey,
    ) {
    }

    public function optimize(OptimizeWaypointsRequest $request): OptimizedWaypoints
    {
        $resposta = $this->http->get(
            Provider::Here,
            Service::RouteOptimization,
            $this->url,
            $this->mapper->toQuery(
                $request->origin,
                $request->destination,
                $request->intermediates,
                $request->mode,
                $this->apiKey,
                $request->objective,
            ),
        );

        $totais = $this->mapper->toTotals($resposta);

        return new OptimizedWaypoints(
            order: $this->mapper->toOrder($resposta, count($request->intermediates)),
            distance: new Distance($totais['distance']),
            duration: new Duration($totais['duration']),
            objective: $request->objective,
            strategy: 'here.findsequence',
        );
    }
}
