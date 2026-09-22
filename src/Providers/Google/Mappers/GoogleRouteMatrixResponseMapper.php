<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntry;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;

final class GoogleRouteMatrixResponseMapper
{
    /**
     * @param int $origens  Quantas origens a requisicao pediu.
     * @param int $destinos Quantos destinos a requisicao pediu.
     *
     * @throws ProviderRequestException quando a resposta nao descreve a grade inteira
     */
    public function toCollection(array $resposta, int $origens, int $destinos): RouteMatrixEntryCollection
    {
        $entradas = [];
        $vistos = [];

        foreach ($resposta as $elemento) {
            if (! is_array($elemento)) {
                continue;
            }

            // O computeRouteMatrix e server-streaming: uma falha depois do stream
            // comecar chega como elemento de erro no meio do array, com HTTP 200.
            // Sem isto o elemento cai em (0,0) — sem indices, com 0 metros e
            // marcado como alcancavel — e a colecao sobrescreve o par verdadeiro.
            if (isset($elemento['error'])) {
                throw new ProviderRequestException(
                    Provider::Google,
                    Service::RouteMatrix,
                    'O Google interrompeu a matriz no meio do stream: '
                        . ($elemento['error']['message'] ?? 'erro sem mensagem'),
                    200,
                    isset($elemento['error']['status']) ? (string) $elemento['error']['status'] : null,
                );
            }

            $origem = (int) ($elemento['originIndex'] ?? 0);
            $destino = (int) ($elemento['destinationIndex'] ?? 0);

            // Contar elementos nao prova que a grade esta completa: indice fora
            // da faixa pedida e par repetido somam certo e deixam buraco, porque
            // a colecao indexa por par e o repetido sobrescreve o anterior.
            if ($origem < 0 || $origem >= $origens || $destino < 0 || $destino >= $destinos) {
                throw new ProviderRequestException(
                    Provider::Google,
                    Service::RouteMatrix,
                    sprintf(
                        'O Google devolveu o par (%d,%d), fora da faixa pedida de %d origens por %d destinos.',
                        $origem,
                        $destino,
                        $origens,
                        $destinos,
                    ),
                    200,
                );
            }

            $par = $origem . ':' . $destino;

            if (isset($vistos[$par])) {
                throw new ProviderRequestException(
                    Provider::Google,
                    Service::RouteMatrix,
                    sprintf('O Google devolveu o par (%d,%d) duplicado.', $origem, $destino),
                    200,
                );
            }

            $vistos[$par] = true;

            // So ROUTE_EXISTS explicito conta como alcancavel: proto3 omite o
            // default do enum, e o default aqui e UNSPECIFIED, nao ROUTE_EXISTS.
            $alcancavel = ($elemento['condition'] ?? null) === 'ROUTE_EXISTS';

            $entradas[] = new RouteMatrixEntry(
                originIndex: $origem,
                destinationIndex: $destino,
                distance: new Distance($alcancavel ? (int) ($elemento['distanceMeters'] ?? 0) : 0),
                duration: new Duration($alcancavel ? $this->segundos($elemento['duration'] ?? null) : 0),
                reachable: $alcancavel,
            );
        }

        // Com todos os pares distintos e dentro da faixa, a contagem certa passa
        // a significar grade completa — e so agora ela prova alguma coisa.
        if (count($entradas) !== $origens * $destinos) {
            throw new ProviderRequestException(
                Provider::Google,
                Service::RouteMatrix,
                sprintf(
                    'Matriz incompleta: o Google devolveu %d de %d pares. '
                    . 'Entregar a matriz parcial esconderia pares que o chamador pediu.',
                    count($entradas),
                    $origens * $destinos,
                ),
                200,
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
