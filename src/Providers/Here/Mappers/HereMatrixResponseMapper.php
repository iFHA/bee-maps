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
     * @param int $origens  Quantas origens a requisicao pediu.
     * @param int $destinos Quantos destinos a requisicao pediu.
     *
     * @throws ProviderRequestException quando a resposta nao traz a grade inteira
     */
    public function toCollection(array $resposta, int $origens, int $destinos): RouteMatrixEntryCollection
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

        $devolvidasOrigens = (int) ($matriz['numOrigins'] ?? 0);
        $devolvidosDestinos = (int) ($matriz['numDestinations'] ?? 0);

        // Comparar o PRODUTO nao enxerga a troca: 3x2 e 2x3 dao 6 nos dois
        // casos, e o resultado sai com origem fantasma e par pedido faltando.
        if ($devolvidasOrigens !== $origens || $devolvidosDestinos !== $destinos) {
            throw new ProviderRequestException(
                Provider::Here,
                Service::RouteMatrix,
                sprintf(
                    'O HERE devolveu uma matriz %dx%d para uma requisicao %dx%d.',
                    $devolvidasOrigens,
                    $devolvidosDestinos,
                    $origens,
                    $destinos,
                ),
                200,
            );
        }

        $total = $origens * $destinos;

        // array_values porque o acesso adiante e por posicao row-major: uma lista
        // com chaves nao sequenciais teria a contagem certa e o indice errado.
        $distancias = array_values($matriz['distances'] ?? []);
        $tempos = array_values($matriz['travelTimes'] ?? []);

        // Medida tem que VIR da resposta. Completar com `?? 0` transformava uma
        // resposta sem `distances` numa matriz inteira de 0 metros marcada como
        // alcancavel — numero inventado que o consumidor nao tem como desconfiar.
        foreach (['distances' => $distancias, 'travelTimes' => $tempos] as $campo => $valores) {
            if (count($valores) !== $total) {
                throw new ProviderRequestException(
                    Provider::Here,
                    Service::RouteMatrix,
                    sprintf(
                        'O HERE devolveu %d medidas em "%s" para uma grade de %d pares.',
                        count($valores),
                        $campo,
                        $total,
                    ),
                    200,
                );
            }
        }

        // O campo errorCodes NAO vem quando todos os pares sao alcancaveis —
        // verificado contra a API. Ausente significa "tudo alcancavel"; presente
        // e incompleto e outra coisa, e nao pode ser completado com `?? 0`.
        $erros = array_values($matriz['errorCodes'] ?? []);

        if ($erros !== [] && count($erros) !== $total) {
            throw new ProviderRequestException(
                Provider::Here,
                Service::RouteMatrix,
                sprintf(
                    'O HERE devolveu %d codigos de erro para uma grade de %d pares.',
                    count($erros),
                    $total,
                ),
                200,
            );
        }

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
                    distance: new Distance($alcancavel ? (int) $distancias[$indice] : 0),
                    duration: new Duration($alcancavel ? (int) $tempos[$indice] : 0),
                    reachable: $alcancavel,
                );
            }
        }

        return new RouteMatrixEntryCollection(...$entradas);
    }
}
