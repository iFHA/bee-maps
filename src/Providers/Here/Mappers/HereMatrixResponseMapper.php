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
        $distancias = $this->medidas($matriz, 'distances', $total);
        $tempos = $this->medidas($matriz, 'travelTimes', $total);

        // O campo errorCodes NAO vem quando todos os pares sao alcancaveis —
        // verificado contra a API. Ausente significa "tudo alcancavel"; presente
        // passa pela mesma validacao dos demais.
        $erros = array_key_exists('errorCodes', $matriz)
            ? $this->medidas($matriz, 'errorCodes', $total)
            : [];

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

    /**
     * Valida um array de medidas pelo VALOR, nao so pela quantidade: contar
     * elementos deixava passar escalar (TypeError no array_values) e null no
     * meio da lista (que virava 0 metros num par marcado como alcancavel).
     *
     * @return list<int|float>
     */
    private function medidas(array $matriz, string $campo, int $total): array
    {
        $valores = $matriz[$campo] ?? [];

        if (! is_array($valores)) {
            throw new ProviderRequestException(
                Provider::Here,
                Service::RouteMatrix,
                sprintf(
                    'O HERE devolveu "%s" como %s, e nao como lista de medidas.',
                    $campo,
                    get_debug_type($valores),
                ),
                200,
            );
        }

        // array_values porque o acesso adiante e por posicao row-major: uma lista
        // com chaves nao sequenciais teria a contagem certa e o indice errado.
        $valores = array_values($valores);

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

        foreach ($valores as $posicao => $valor) {
            if (! is_numeric($valor)) {
                throw new ProviderRequestException(
                    Provider::Here,
                    Service::RouteMatrix,
                    sprintf(
                        'O HERE devolveu a medida %d de "%s" como %s, e nao como numero.',
                        $posicao,
                        $campo,
                        get_debug_type($valor),
                    ),
                    200,
                );
            }
        }

        return $valores;
    }
}
