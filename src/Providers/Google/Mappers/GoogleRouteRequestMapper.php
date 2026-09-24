<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

final class GoogleRouteRequestMapper
{
    public function toPayload(RouteRequest $request, string $language): array
    {
        $payload = [
            'origin' => $this->waypoint($request->origin),
            'destination' => $this->waypoint($request->destination),
            'travelMode' => $this->mode($request->mode),
            'languageCode' => $language,
        ];

        if ($request->intermediates !== []) {
            $payload['intermediates'] = array_map($this->waypoint(...), $request->intermediates);
        }

        if ($request->optimizeIntermediates) {
            $payload['optimizeWaypointOrder'] = true;
        }

        if ($request->alternatives > 0) {
            // O computeRoutes so aceita liga/desliga: a QUANTIDADE fica a
            // criterio dele. O numero do request e honrado no HERE, que tem
            // parametro proprio. Verificado em 2026-09-23: com intermediarios
            // o Google TAMBEM devolve alternativas, ao contrario do que a
            // documentacao dele afirma.
            $payload['computeAlternativeRoutes'] = true;
        }

        return $payload;
    }

    /**
     * O field mask e obrigatorio no computeRoutes e cobrado por campo pedido:
     * geometria e pernas so entram quando a requisicao pede.
     */
    public function fieldMask(RouteRequest $request): string
    {
        $fields = ['routes.distanceMeters', 'routes.duration'];

        if ($request->includePolyline) {
            $fields[] = 'routes.polyline.encodedPolyline';
        }

        if ($request->includeLegs) {
            $fields[] = 'routes.legs.distanceMeters';
            $fields[] = 'routes.legs.duration';
            $fields[] = 'routes.legs.startLocation';
            $fields[] = 'routes.legs.endLocation';

            if ($request->includePolyline) {
                $fields[] = 'routes.legs.polyline.encodedPolyline';
            }
        }

        if ($request->optimizeIntermediates) {
            $fields[] = 'routes.optimizedIntermediateWaypointIndex';
        }

        return implode(',', $fields);
    }

    private function waypoint(Coordinates $point): array
    {
        return [
            'location' => [
                'latLng' => [
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                ],
            ],
        ];
    }

    private function mode(TravelMode $mode): string
    {
        return match ($mode) {
            TravelMode::Drive => 'DRIVE',
            TravelMode::TwoWheeler => 'TWO_WHEELER',
            TravelMode::Bicycle => 'BICYCLE',
            TravelMode::Walk => 'WALK',
        };
    }
}
