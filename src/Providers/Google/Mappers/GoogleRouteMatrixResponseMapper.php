<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntry;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;

final class GoogleRouteMatrixResponseMapper
{
    public function toCollection(array $resposta): RouteMatrixEntryCollection
    {
        $entradas = [];

        foreach ($resposta as $elemento) {
            if (! is_array($elemento)) {
                continue;
            }

            // A API devolve os elementos FORA DE ORDEM — numa matriz 2x2 real,
            // (0,1) veio antes de (0,0). Os indices sao a unica fonte de verdade;
            // a posicao no array nao significa nada.
            $alcancavel = ($elemento['condition'] ?? 'ROUTE_EXISTS') === 'ROUTE_EXISTS';

            $entradas[] = new RouteMatrixEntry(
                originIndex: (int) ($elemento['originIndex'] ?? 0),
                destinationIndex: (int) ($elemento['destinationIndex'] ?? 0),
                distance: new Distance((int) ($elemento['distanceMeters'] ?? 0)),
                duration: new Duration($this->segundos($elemento['duration'] ?? null)),
                reachable: $alcancavel,
            );
        }

        return new RouteMatrixEntryCollection(...$entradas);
    }

    /**
     * O Google serializa duracao como string de protobuf ("490s").
     */
    private function segundos(string|int|null $valor): int
    {
        return match (true) {
            $valor === null => 0,
            is_int($valor) => $valor,
            default => (int) rtrim($valor, 's'),
        };
    }
}
