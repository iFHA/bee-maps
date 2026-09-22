<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Services;

use BeeDelivery\BeeMaps\Contracts\Services\RouteOptimization;
use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\OptimizedWaypoints;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Providers\Google\Optimization\OptimizationStrategy;
use Closure;

/**
 * Nao implementa otimizacao: escolhe e delega. Qual classe atende o MinDistance
 * e decisao do GoogleProvider, a partir do config — aqui chega ja resolvido.
 *
 * Recebe FABRICAS, nao instancias: a estrategia de distancia pode exigir service
 * account e o pacote google/apiclient, e construi-la junto com a de tempo fazia
 * um config incompleto de fleet_routing derrubar tambem o MinTravelTime, que
 * nao depende de nenhum dos dois.
 */
final class GoogleRouteOptimization implements RouteOptimization
{
    /**
     * @param Closure(): OptimizationStrategy $porTempo
     * @param Closure(): OptimizationStrategy $porDistancia
     */
    public function __construct(
        private readonly Closure $porTempo,
        private readonly Closure $porDistancia,
    ) {
    }

    public function optimize(OptimizeWaypointsRequest $request): OptimizedWaypoints
    {
        $estrategia = match ($request->objective) {
            OptimizationObjective::MinTravelTime => ($this->porTempo)(),
            OptimizationObjective::MinDistance => ($this->porDistancia)(),
        };

        return $estrategia->optimize($request);
    }
}
