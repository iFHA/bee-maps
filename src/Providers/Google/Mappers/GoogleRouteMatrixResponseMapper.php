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
     * @param int $esperados Quantos pares a requisicao pediu (origens x destinos).
     *
     * @throws ProviderRequestException quando a resposta nao descreve a matriz inteira
     */
    public function toCollection(array $resposta, int $esperados): RouteMatrixEntryCollection
    {
        $entradas = [];

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

            // A API devolve os elementos FORA DE ORDEM — numa matriz 2x2 real,
            // (0,1) veio antes de (0,0). Os indices sao a unica fonte de verdade;
            // a posicao no array nao significa nada.
            //
            // So ROUTE_EXISTS explicito conta como alcancavel: proto3 omite o
            // default do enum, e o default aqui e UNSPECIFIED, nao ROUTE_EXISTS.
            $alcancavel = ($elemento['condition'] ?? null) === 'ROUTE_EXISTS';

            $entradas[] = new RouteMatrixEntry(
                originIndex: (int) ($elemento['originIndex'] ?? 0),
                destinationIndex: (int) ($elemento['destinationIndex'] ?? 0),
                distance: new Distance($alcancavel ? (int) ($elemento['distanceMeters'] ?? 0) : 0),
                duration: new Duration($alcancavel ? $this->segundos($elemento['duration'] ?? null) : 0),
                reachable: $alcancavel,
            );
        }

        if (count($entradas) !== $esperados) {
            throw new ProviderRequestException(
                Provider::Google,
                Service::RouteMatrix,
                sprintf(
                    'Matriz incompleta: o Google devolveu %d de %d elementos. '
                    . 'Entregar a matriz parcial esconderia pares que o chamador pediu.',
                    count($entradas),
                    $esperados,
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
