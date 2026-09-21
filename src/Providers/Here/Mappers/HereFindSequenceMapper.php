<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;

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
            'mode' => 'fastest;' . $this->modo($request->mode),
            'apiKey' => $apiKey,
        ];

        foreach ($request->intermediates as $indice => $ponto) {
            $nome = 'destination' . ($indice + 1);

            $query[$nome] = $nome . ';' . $ponto->toString();
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    public function toOrder(array $resposta): array
    {
        $waypoints = $resposta['results'][0]['waypoints'] ?? null;

        if ($waypoints === null || $waypoints === []) {
            throw new InvalidRequestException('O findsequence do HERE nao devolveu sequencia de waypoints.');
        }

        usort($waypoints, fn (array $a, array $b) => ($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0));

        $ordem = [];

        foreach ($waypoints as $waypoint) {
            if (preg_match('/^destination(\d+)$/', (string) ($waypoint['id'] ?? ''), $partes) === 1) {
                $ordem[] = (int) $partes[1] - 1;
            }
        }

        return $ordem;
    }

    private function modo(TravelMode $modo): string
    {
        return match ($modo) {
            TravelMode::Drive => 'car',
            TravelMode::TwoWheeler => 'scooter',
            TravelMode::Bicycle => 'bicycle',
            TravelMode::Walk => 'pedestrian',
        };
    }
}
