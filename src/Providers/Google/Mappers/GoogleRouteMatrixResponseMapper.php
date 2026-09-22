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

        // Erro no topo do corpo, sem embrulho de array. Chega com HTTP 200, entao
        // o traduzirErro do MapsHttpClient nunca roda — e sem isto o corpo inteiro
        // e iterado como se fosse elemento de matriz. O MapsHttpClient ja trata as
        // duas formas (`$corpo['error'] ?? $corpo[0]['error']`); aqui e o espelho.
        if (isset($resposta['error']) && is_array($resposta['error'])) {
            throw $this->erroDaApi($resposta['error'], 'O Google devolveu erro no corpo da matriz com HTTP 200');
        }

        foreach ($resposta as $elemento) {
            if (! is_array($elemento)) {
                continue;
            }

            // O computeRouteMatrix e server-streaming: uma falha depois do stream
            // comecar chega como elemento de erro no meio do array, com HTTP 200.
            // Sem isto o elemento cai em (0,0) — sem indices, com 0 metros e
            // marcado como alcancavel — e a colecao sobrescreve o par verdadeiro.
            if (isset($elemento['error']) && is_array($elemento['error'])) {
                throw $this->erroDaApi($elemento['error'], 'O Google interrompeu a matriz no meio do stream');
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
     * @param array<string, mixed> $erro
     */
    private function erroDaApi(array $erro, string $contexto): ProviderRequestException
    {
        return new ProviderRequestException(
            Provider::Google,
            Service::RouteMatrix,
            $contexto . ': ' . ($erro['message'] ?? 'erro sem mensagem'),
            200,
            isset($erro['status']) ? (string) $erro['status'] : null,
        );
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
