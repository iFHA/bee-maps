<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Optimization;

use BeeDelivery\BeeMaps\Contracts\Services\RouteMatrix;
use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\OptimizedWaypoints;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\Tsp\NearestNeighbourTour;
use BeeDelivery\BeeMaps\Support\Tsp\TourMeasure;
use BeeDelivery\BeeMaps\Support\Tsp\TourResult;
use BeeDelivery\BeeMaps\Support\Tsp\TwoOptRefinement;

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
        private readonly MapsHttpClient $http,
        private readonly RouteMatrix $matrix,
        private readonly NearestNeighbourTour $tsp,
        private readonly TwoOptRefinement $refinement,
        private readonly TourMeasure $measure,
    ) {
    }

    public function optimize(OptimizeWaypointsRequest $request): OptimizedWaypoints
    {
        // A chamada sai pelo contrato RouteMatrix, que emite o evento em nome
        // DELE. Sem agrupar, uma otimizacao por distancia no Google nao aparece
        // nas metricas de RouteOptimization e infla as de RouteMatrix — e a
        // comparacao de latencia entre providers le isso como se o Google nao
        // tivesse otimizado nada. Mesmo motivo do agrupamento no HereRouting.
        return $this->http->operation(
            Provider::Google,
            Service::RouteOptimization,
            fn (): OptimizedWaypoints => $this->resolve($request),
        );
    }

    private function resolve(OptimizeWaypointsRequest $request): OptimizedWaypoints
    {
        $points = [$request->origin, ...$request->intermediates];
        $end = null;

        if ($request->destination !== null) {
            $points[] = $request->destination;
            $end = count($points) - 1;
        }

        $matrixResult = $this->matrix->matrix(new RouteMatrixRequest($points, $points, $request->mode));

        $tour = $this->refinement->refine(
            $matrixResult,
            0,
            $end,
            $request->objective,
            $this->tsp->tour($matrixResult, 0, $end, $request->objective),
        );

        $tour = $this->orSubmittedOrder($tour, $matrixResult, $end, $request);

        // O TourResult fala em indices da MATRIZ, onde a posicao 0 e a origem.
        // O contrato fala em indices de $intermediates. O deslocamento de 1 e a
        // traducao, e e responsabilidade desta classe: o TSP nao sabe que existe
        // um contrato RouteOptimization.
        $order = array_map(static fn (int $index): int => $index - 1, $tour->order);

        return new OptimizedWaypoints(
            order: $order,
            distance: $tour->distance,
            duration: $tour->duration,
            objective: $request->objective,
            strategy: 'google.matrix_tsp',
        );
    }

    /**
     * Devolve a ordem lancada pelo lojista quando a rota otimizada nao ganha dela.
     *
     * Vizinho-mais-proximo nao tem garantia de otimalidade, e o 2-opt so garante
     * que a rota nao piorou em relacao a do guloso — nenhum dos dois garante
     * ganho sobre a ordem que veio no pedido. Sem esta comparacao a "otimizacao"
     * pode devolver rota mais longa que a rota sem otimizacao nenhuma, e onde a
     * taxa sai da distancia isso chega ao lojista como cobranca maior por ter
     * pedido para otimizar (BEE-12720 no pacote legado).
     *
     * Empate mantem a ordem original: reordenar parada sem ganho nenhum so
     * contraria quem lancou o pedido.
     */
    private function orSubmittedOrder(
        TourResult $tour,
        RouteMatrixEntryCollection $matrix,
        ?int $end,
        OptimizeWaypointsRequest $request,
    ): TourResult {
        // A matriz e [origem, ...intermediarios, destino?], entao a ordem lancada
        // e sempre 1..n — os indices dos intermediarios como o chamador os passou.
        $submitted = $this->measure->measure($matrix, 0, $end, range(1, count($request->intermediates)));

        // Ordem lancada que atravessa perna sem rota nao tem medida, e ai nao ha
        // o que comparar: vale a rota que o otimizador conseguiu montar.
        if ($submitted === null) {
            return $tour;
        }

        return $submitted->cost($request->objective) <= $tour->cost($request->objective)
            ? $submitted
            : $tour;
    }
}
