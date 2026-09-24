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
     * @param int $origins  Quantas origens a requisicao pediu.
     * @param int $destinations Quantos destinos a requisicao pediu.
     *
     * @throws ProviderRequestException quando a resposta nao traz a grade inteira
     */
    public function toCollection(array $response, int $origins, int $destinations): RouteMatrixEntryCollection
    {
        $matrix = $response['matrix'] ?? null;

        // Uma resposta 2xx sem `matrix` e um envelope de job, nao uma matriz
        // vazia. Devolver colecao vazia aqui faria o chamador ler "nenhum par
        // tem rota" onde a verdade e "a matriz nao foi calculada".
        if (! is_array($matrix)) {
            throw new ProviderRequestException(
                Provider::Here,
                Service::RouteMatrix,
                'O HERE respondeu sem a matriz (provavelmente um envelope de job assincrono). '
                . 'Confira se a chamada esta indo com async=false.',
                200,
            );
        }

        $returnedOrigins = (int) ($matrix['numOrigins'] ?? 0);
        $returnedDestinations = (int) ($matrix['numDestinations'] ?? 0);

        // Comparar o PRODUTO nao enxerga a troca: 3x2 e 2x3 dao 6 nos dois
        // casos, e o resultado sai com origem fantasma e par pedido faltando.
        if ($returnedOrigins !== $origins || $returnedDestinations !== $destinations) {
            throw new ProviderRequestException(
                Provider::Here,
                Service::RouteMatrix,
                sprintf(
                    'O HERE devolveu uma matriz %dx%d para uma requisicao %dx%d.',
                    $returnedOrigins,
                    $returnedDestinations,
                    $origins,
                    $destinations,
                ),
                200,
            );
        }

        $total = $origins * $destinations;
        $distances = $this->measurements($matrix, 'distances', $total);
        $times = $this->measurements($matrix, 'travelTimes', $total);

        // O campo errorCodes NAO vem quando todos os pares sao alcancaveis —
        // verificado contra a API. Lista vazia e null carregam a MESMA informacao
        // que a ausencia: recusa-los seria rejeitar uma matriz completa so porque
        // o HERE serializou o caso vazio em vez de omitir o campo. Qualquer outro
        // valor passa pela mesma validacao dos demais.
        $errors = ($matrix['errorCodes'] ?? []) === []
            ? []
            : $this->measurements($matrix, 'errorCodes', $total);

        $entries = [];

        for ($origin = 0; $origin < $origins; $origin++) {
            for ($destination = 0; $destination < $destinations; $destination++) {
                // Arrays achatados em row-major.
                $index = $origin * $destinations + $destination;

                $reachable = (int) ($errors[$index] ?? 0) === 0;

                // Par sem rota vem com distancia e duracao zeradas, e nao com o
                // numero que o HERE deixou no array: medida de rota inexistente
                // e dado que mente, e o consumidor ramifica em cima dela.
                $entries[] = new RouteMatrixEntry(
                    originIndex: $origin,
                    destinationIndex: $destination,
                    distance: new Distance($reachable ? (int) $distances[$index] : 0),
                    duration: new Duration($reachable ? (int) $times[$index] : 0),
                    reachable: $reachable,
                );
            }
        }

        return new RouteMatrixEntryCollection(...$entries);
    }

    /**
     * Valida um array de medidas pelo VALOR, nao so pela quantidade: contar
     * elementos deixava passar escalar (TypeError no array_values) e null no
     * meio da lista (que virava 0 metros num par marcado como alcancavel).
     *
     * @return list<int|float>
     */
    private function measurements(array $matrix, string $field, int $total): array
    {
        $values = $matrix[$field] ?? [];

        if (! is_array($values)) {
            throw new ProviderRequestException(
                Provider::Here,
                Service::RouteMatrix,
                sprintf(
                    'O HERE devolveu "%s" como %s, e nao como lista.',
                    $field,
                    get_debug_type($values),
                ),
                200,
            );
        }

        // array_values porque o acesso adiante e por posicao row-major: uma lista
        // com chaves nao sequenciais teria a contagem certa e o indice errado.
        $values = array_values($values);

        if (count($values) !== $total) {
            throw new ProviderRequestException(
                Provider::Here,
                Service::RouteMatrix,
                sprintf(
                    'O HERE devolveu %d valores em "%s" para uma grade de %d pares.',
                    count($values),
                    $field,
                    $total,
                ),
                200,
            );
        }

        foreach ($values as $position => $value) {
            if (! is_numeric($value)) {
                throw new ProviderRequestException(
                    Provider::Here,
                    Service::RouteMatrix,
                    sprintf(
                        'O HERE devolveu o valor %d de "%s" como %s, e nao como numero.',
                        $position,
                        $field,
                        get_debug_type($value),
                    ),
                    200,
                );
            }
        }

        return $values;
    }
}
