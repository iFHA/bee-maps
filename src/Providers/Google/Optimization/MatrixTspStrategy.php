<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Optimization;

use BeeDelivery\BeeMaps\Contracts\Services\RouteMatrix;
use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\OptimizedWaypoints;
use BeeDelivery\BeeMaps\Support\Tsp\NearestNeighbourTour;

/**
 * Matriz de rotas do proprio pacote + TSP guloso local. E o caminho default do
 * MinDistance, e o unico que nao depende de credencial alem da chave do Google.
 *
 * O limite de 625 elementos da matriz ja cobre o teto de 25 paradas do pacote
 * legado (25 x 25 = 625): a guarda existente produz MatrixTooLargeException sem
 * precisar de validacao propria aqui.
 */
final class MatrixTspStrategy implements OptimizationStrategy
{
    public function __construct(
        private readonly RouteMatrix $matrix,
        private readonly NearestNeighbourTour $tsp,
    ) {
    }

    public function optimize(OptimizeWaypointsRequest $request): OptimizedWaypoints
    {
        $pontos = [$request->origin, ...$request->intermediates];
        $fim = null;

        if ($request->destination !== null) {
            $pontos[] = $request->destination;
            $fim = count($pontos) - 1;
        }

        $matriz = $this->matrix->matrix(new RouteMatrixRequest($pontos, $pontos, $request->mode));

        $tour = $this->tsp->tour($matriz, 0, $fim, $request->objective);

        // O TourResult fala em indices da MATRIZ, onde a posicao 0 e a origem.
        // O contrato fala em indices de $intermediates. O deslocamento de 1 e a
        // traducao, e e responsabilidade desta classe: o TSP nao sabe que existe
        // um contrato RouteOptimization.
        $ordem = array_map(static fn (int $indice): int => $indice - 1, $tour->order);

        return new OptimizedWaypoints(
            order: $ordem,
            distance: $tour->distance,
            duration: $tour->duration,
            objective: $request->objective,
            strategy: 'google.matrix_tsp',
        );
    }
}
