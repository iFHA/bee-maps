<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntry;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;

final class HereMatrixResponseMapper
{
    /**
     * @param int $esperados Quantos pares a requisicao pediu (origens x destinos).
     *
     * @throws ProviderRequestException quando a resposta nao traz a matriz inteira
     */
    public function toCollection(array $resposta, int $esperados): RouteMatrixEntryCollection
    {
        $matriz = $resposta['matrix'] ?? null;

        // Uma resposta 2xx sem `matrix` e um envelope de job, nao uma matriz
        // vazia. Devolver colecao vazia aqui faria o chamador ler "nenhum par
        // tem rota" onde a verdade e "a matriz nao foi calculada".
        if (! is_array($matriz)) {
            throw new ProviderRequestException(
                Provider::Here,
                Service::RouteMatrix,
                'O HERE respondeu sem a matriz (provavelmente um envelope de job assincrono). '
                . 'Confira se a chamada esta indo com async=false.',
                200,
            );
        }

        $origens = (int) ($matriz['numOrigins'] ?? 0);
        $destinos = (int) ($matriz['numDestinations'] ?? 0);

        if ($origens * $destinos !== $esperados) {
            throw new ProviderRequestException(
                Provider::Here,
                Service::RouteMatrix,
                sprintf(
                    'Matriz incompleta: o HERE devolveu %d de %d elementos.',
                    $origens * $destinos,
                    $esperados,
                ),
                200,
            );
        }

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
