<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntry;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;

final class HereMatrixResponseMapper
{
    public function toCollection(array $resposta): RouteMatrixEntryCollection
    {
        $matriz = $resposta['matrix'] ?? null;

        if ($matriz === null) {
            return new RouteMatrixEntryCollection();
        }

        $origens = (int) ($matriz['numOrigins'] ?? 0);
        $destinos = (int) ($matriz['numDestinations'] ?? 0);
        $distancias = $matriz['distances'] ?? [];
        $tempos = $matriz['travelTimes'] ?? [];

        // O campo errorCodes NAO vem quando todos os pares sao alcancaveis —
        // verificado contra a API. Tratar a ausencia como erro marcaria a matriz
        // inteira como inalcancavel.
        $erros = $matriz['errorCodes'] ?? [];

        $entradas = [];

        for ($origem = 0; $origem < $origens; $origem++) {
            for ($destino = 0; $destino < $destinos; $destino++) {
                // Arrays achatados em row-major.
                $indice = $origem * $destinos + $destino;

                $alcancavel = (int) ($erros[$indice] ?? 0) === 0;

                // Par sem rota vem com distancia e duracao zeradas, e nao com o
                // numero que o HERE deixou no array: medida de rota inexistente
                // e dado que mente, e o consumidor ramifica em cima dela.
                $entradas[] = new RouteMatrixEntry(
                    originIndex: $origem,
                    destinationIndex: $destino,
                    distance: new Distance($alcancavel ? (int) ($distancias[$indice] ?? 0) : 0),
                    duration: new Duration($alcancavel ? (int) ($tempos[$indice] ?? 0) : 0),
                    reachable: $alcancavel,
                );
            }
        }

        return new RouteMatrixEntryCollection(...$entradas);
    }
}
