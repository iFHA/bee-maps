<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Optimization;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\OptimizedWaypoints;

/**
 * Interface INTERNA do provider Google. Nao aparece no contrato publico: o
 * chamador pede o objetivo, nao a estrategia — e o HERE nao tem equivalente
 * para nenhuma destas.
 */
interface OptimizationStrategy
{
    public function optimize(OptimizeWaypointsRequest $request): OptimizedWaypoints;
}
