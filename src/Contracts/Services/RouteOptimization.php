<?php

namespace BeeDelivery\BeeMaps\Contracts\Services;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\OptimizedWaypoints;

interface RouteOptimization
{
    /**
     * Resolve a ORDEM de visita das paradas. Nao devolve geometria — para isso
     * existe o contrato Routing.
     *
     * A ordem devolvida e sempre permutacao completa dos intermediarios
     * enviados: ordem parcial nunca e devolvida, porque o chamador a usa para
     * reordenar a propria lista e um buraco apagaria paradas em silencio.
     *
     * @throws \BeeDelivery\BeeMaps\Exceptions\InvalidRequestException
     *         quando o provider nao consegue ordenar os pontos informados
     * @throws \BeeDelivery\BeeMaps\Exceptions\ProviderRequestException
     *         quando a resposta nao descreve uma ordem completa e valida
     */
    public function optimize(OptimizeWaypointsRequest $request): OptimizedWaypoints;
}
