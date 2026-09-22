<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Services;

use BeeDelivery\BeeMaps\Contracts\Services\RouteOptimization;
use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\OptimizedWaypoints;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Providers\Google\Optimization\OptimizationStrategy;

/**
 * Nao implementa otimizacao: escolhe e delega. Qual classe atende o MinDistance
 * e decisao do GoogleProvider, a partir do config — aqui chega ja resolvido.
 */
final class GoogleRouteOptimization implements RouteOptimization
{
    public function __construct(
        private readonly OptimizationStrategy $porTempo,
        private readonly OptimizationStrategy $porDistancia,
    ) {
    }

    public function optimize(OptimizeWaypointsRequest $request): OptimizedWaypoints
    {
        return match ($request->objective) {
            OptimizationObjective::MinTravelTime => $this->porTempo->optimize($request),
            OptimizationObjective::MinDistance => $this->porDistancia->optimize($request),
        };
    }
}
