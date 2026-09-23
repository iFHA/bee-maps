<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Providers\Here\HereTransportMode;

final class HereRouteRequestMapper
{
    /**
     * @param list<int>|null $ordemIntermediarios Indices dos intermediarios na ordem em
     *                                            que devem ser visitados. Null mantem a
     *                                            ordem recebida. O /v8/routes NAO otimiza
     *                                            sozinho: a ordem vem do findsequence.
     */
    public function toQuery(RouteRequest $request, string $language, ?array $ordemIntermediarios = null): array
    {
        $retorno = ['summary'];

        if ($request->includePolyline) {
            $retorno[] = 'polyline';
        }

        $query = [
            'origin' => $request->origin->toString(),
            'destination' => $request->destination->toString(),
            'transportMode' => HereTransportMode::from($request->mode),
            'return' => implode(',', $retorno),
            'lang' => $language,
        ];

        if ($request->alternatives > 0) {
            // Aqui o numero e honrado: o /v8/routes tem parametro proprio e
            // devolve ate N+1 rotas. Teto de 6 validado no RouteRequest.
            $query['alternatives'] = $request->alternatives;
        }

        $intermediarios = $request->intermediates;

        if ($ordemIntermediarios !== null) {
            if (count($ordemIntermediarios) !== count($request->intermediates)) {
                throw new InvalidRequestException(sprintf(
                    'Ordem de intermediarios com %d itens para %d waypoints: montar a rota assim '
                    . 'descartaria paradas em silencio.',
                    count($ordemIntermediarios),
                    count($request->intermediates),
                ));
            }

            $intermediarios = array_map(
                fn (int $indice) => $request->intermediates[$indice],
                $ordemIntermediarios,
            );
        }

        if ($intermediarios !== []) {
            // O MapsHttpClient serializa array como chave repetida (via=a&via=b),
            // que e o formato que o /v8/routes exige.
            $query['via'] = array_map(fn ($ponto) => $ponto->toString(), $intermediarios);
        }

        return $query;
    }
}
