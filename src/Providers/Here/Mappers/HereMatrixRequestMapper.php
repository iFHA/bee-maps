<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\Providers\Here\HereTransportMode;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

final class HereMatrixRequestMapper
{
    public function toPayload(RouteMatrixRequest $request): array
    {
        return [
            'origins' => array_map($this->point(...), $request->origins),
            'destinations' => array_map($this->point(...), $request->destinations),
            // O autoCircle e obrigatorio (nao ha equivalente no Google) e o
            // proprio HERE calcula centro e raio, devolvendo na resposta o que usou.
            'regionDefinition' => ['type' => 'autoCircle'],
            'matrixAttributes' => ['distances', 'travelTimes'],
            'transportMode' => HereTransportMode::from($request->mode),
        ];
    }

    private function point(Coordinates $point): array
    {
        return ['lat' => $point->latitude, 'lng' => $point->longitude];
    }
}
