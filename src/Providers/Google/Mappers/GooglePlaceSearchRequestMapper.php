<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\PlaceSearchRequest;
use BeeDelivery\BeeMaps\Support\CountryCode;

final class GooglePlaceSearchRequestMapper
{
    public function toPayload(PlaceSearchRequest $request, string $language, string $region): array
    {
        $payload = [
            'textQuery' => $request->query,
            'languageCode' => $language,
            'regionCode' => CountryCode::toAlpha2($request->region ?? $region),
        ];

        if ($request->near !== null) {
            $payload['locationBias'] = [
                'circle' => [
                    'center' => [
                        'latitude' => $request->near->latitude,
                        'longitude' => $request->near->longitude,
                    ],
                ],
            ];
        }

        return $payload;
    }

    /**
     * places.addressComponents pertence ao SKU Enterprise do Text Search, mais
     * caro que o Basic. E pedido de proposito (D16): sem ele, o Address do
     * Google viria so com `formatted` enquanto o HERE devolve estruturado, e a
     * assimetria contaminaria a comparacao de qualidade da POC.
     */
    public function fieldMask(): string
    {
        return implode(',', [
            'places.id',
            'places.displayName.text',
            'places.formattedAddress',
            'places.location',
            'places.addressComponents',
        ]);
    }
}
