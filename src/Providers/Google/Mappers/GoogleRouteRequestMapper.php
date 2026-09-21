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
            'travelMode' => $this->modo($request->mode),
            'languageCode' => $language,
        ];

        if ($request->intermediates !== []) {
            $payload['intermediates'] = array_map($this->waypoint(...), $request->intermediates);
        }

        if ($request->optimizeIntermediates) {
            $payload['optimizeWaypointOrder'] = true;
        }

        return $payload;
    }

    /**
     * O field mask e obrigatorio no computeRoutes e cobrado por campo pedido:
     * geometria e pernas so entram quando a requisicao pede.
     */
    public function fieldMask(RouteRequest $request): string
    {
        $campos = ['routes.distanceMeters', 'routes.duration'];

        if ($request->includePolyline) {
            $campos[] = 'routes.polyline.encodedPolyline';
        }

        if ($request->includeLegs) {
            $campos[] = 'routes.legs.distanceMeters';
            $campos[] = 'routes.legs.duration';
            $campos[] = 'routes.legs.startLocation';
            $campos[] = 'routes.legs.endLocation';

            if ($request->includePolyline) {
                $campos[] = 'routes.legs.polyline.encodedPolyline';
            }
        }

        if ($request->optimizeIntermediates) {
            $campos[] = 'routes.optimizedIntermediateWaypointIndex';
        }

        return implode(',', $campos);
    }

    private function waypoint(Coordinates $ponto): array
    {
        return [
            'location' => [
                'latLng' => [
                    'latitude' => $ponto->latitude,
                    'longitude' => $ponto->longitude,
                ],
            ],
        ];
    }

    private function modo(TravelMode $modo): string
    {
        return match ($modo) {
            TravelMode::Drive => 'DRIVE',
            TravelMode::TwoWheeler => 'TWO_WHEELER',
            TravelMode::Bicycle => 'BICYCLE',
            TravelMode::Walk => 'WALK',
        };
    }
}
