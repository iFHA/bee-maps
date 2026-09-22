<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Providers\Here\HereTransportMode;

/**
 * Waypoints Sequence API: resolve a ordem de visita (TSP) mas nao devolve a
 * rota — por isso toda rota otimizada no HERE custa duas chamadas upstream.
 *
 * Os waypoints intermediarios sao nomeados destination1..destinationN, e a
 * resposta devolve os mesmos ids com o campo `sequence`. A traducao de volta
 * para indices do array original e o que mantem o contrato do pacote estavel.
 */
final class HereFindSequenceMapper
{
    public function toQuery(RouteRequest $request, string $apiKey): array
    {
        // Cada ponto vai como `WaypointId;lat,lng`. O id nao e cosmetico: e o que
        // a resposta ecoa em waypoints[].id, e o unico jeito de saber qual
        // intermediario ficou em qual posicao. Sem ele, toOrder() devolve [].
        $query = [
            'start' => 'start;' . $request->origin->toString(),
            'end' => 'end;' . $request->destination->toString(),
            'mode' => 'fastest;' . HereTransportMode::from($request->mode),
            'apiKey' => $apiKey,
        ];

        foreach ($request->intermediates as $indice => $ponto) {
            $nome = 'destination' . ($indice + 1);

            $query[$nome] = $nome . ';' . $ponto->toString();
        }

        return $query;
    }

    /**
     * @param int $totalIntermediarios Quantos waypoints intermediarios foram enviados.
     *
     * @return list<int> Permutacao completa de 0..N-1, na ordem de visita.
     *
     * @throws InvalidRequestException quando a resposta nao descreve uma ordem
     *         completa e valida. Devolver ordem parcial e pior que falhar: o
     *         request mapper montaria uma rota sem parte das paradas.
     */
    public function toOrder(array $resposta, int $totalIntermediarios): array
    {
        $waypoints = $resposta['results'][0]['waypoints'] ?? null;

        if ($waypoints === null || $waypoints === []) {
            throw new InvalidRequestException('O findsequence do HERE nao devolveu sequencia de waypoints.');
        }

        usort($waypoints, fn (array $a, array $b) => ($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0));

        $ordem = [];

        foreach ($waypoints as $waypoint) {
            if (preg_match('/^destination(\d+)$/', (string) ($waypoint['id'] ?? ''), $partes) !== 1) {
                continue;
            }

            $indice = (int) $partes[1] - 1;

            if ($indice < 0 || $indice >= $totalIntermediarios || in_array($indice, $ordem, true)) {
                throw new InvalidRequestException(sprintf(
                    'O findsequence do HERE devolveu o waypoint "%s", fora da faixa de %d intermediarios enviados.',
                    (string) ($waypoint['id'] ?? ''),
                    $totalIntermediarios,
                ));
            }

            $ordem[] = $indice;
        }

        if (count($ordem) !== $totalIntermediarios) {
            throw new InvalidRequestException(sprintf(
                'O findsequence do HERE devolveu %d de %d waypoints intermediarios; '
                . 'seguir com ordem incompleta apagaria paradas da rota.',
                count($ordem),
                $totalIntermediarios,
            ));
        }

        return $ordem;
    }
}
