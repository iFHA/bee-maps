<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

final class GoogleRouteMatrixRequestMapper
{
    public function toPayload(RouteMatrixRequest $request): array
    {
        return [
            'origins' => array_map($this->waypoint(...), $request->origins),
            'destinations' => array_map($this->waypoint(...), $request->destinations),
            'travelMode' => $this->mode($request->mode),
        ];
    }

    /**
     * Sem o prefixo "routes.": ao contrario do computeRoutes, a resposta do
     * computeRouteMatrix e um array de elementos na raiz do corpo.
     */
    public function fieldMask(): string
    {
        return 'originIndex,destinationIndex,distanceMeters,duration,condition';
    }

    private function waypoint(Coordinates $point): array
    {
        return [
            'waypoint' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $point->latitude,
                        'longitude' => $point->longitude,
                    ],
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
