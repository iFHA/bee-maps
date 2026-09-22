<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;

final readonly class OptimizedWaypoints
{
    /**
     * @param list<int>             $order     Permutacao completa de 0..N-1 sobre os
     *                                         intermediarios do request, na ordem de
     *                                         visita. Origem e destino nao aparecem:
     *                                         sao pontos fixos, nao resultado.
     * @param OptimizationObjective $objective O que foi pedido.
     * @param string                $strategy  Por qual caminho veio: 'google.routes',
     *                                         'google.matrix_tsp', 'google.fleet_routing'
     *                                         ou 'here.findsequence'. Sem isto o evento
     *                                         MapRequestCompleted nao distingue as tres
     *                                         estrategias do Google.
     */
    public function __construct(
        public array $order,
        public Distance $distance,
        public Duration $duration,
        public OptimizationObjective $objective,
        public string $strategy,
    ) {
    }
}
