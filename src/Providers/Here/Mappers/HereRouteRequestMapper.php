<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;

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
            'transportMode' => $this->modo($request->mode),
            'return' => implode(',', $retorno),
            'lang' => $language,
        ];

        $intermediarios = $request->intermediates;

        if ($ordemIntermediarios !== null) {
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
