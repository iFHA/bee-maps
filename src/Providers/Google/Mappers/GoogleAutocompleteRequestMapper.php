<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;

final class GoogleAutocompleteRequestMapper
{
    public function toPayload(AutocompleteRequest $request, string $language): array
    {
        $payload = [
            'input' => $request->query,
            'languageCode' => $request->language ?? $language,
        ];

        if ($request->near !== null) {
            $payload['locationRestriction'] = [
                'circle' => [
                    'center' => [
                        'latitude' => $request->near->latitude,
                        'longitude' => $request->near->longitude,
                    ],
                    'radius' => $request->radiusMeters,
                ],
            ];
        }

        if ($request->countries !== []) {
            $payload['includedRegionCodes'] = array_values($request->countries);
        }

        return $payload;
    }

    public function fieldMask(): string
    {
        return implode(',', [
            'suggestions.placePrediction.text.text',
            'suggestions.placePrediction.placeId',
            'suggestions.placePrediction.structuredFormat.mainText.text',
            'suggestions.placePrediction.structuredFormat.secondaryText.text',
            'suggestions.placePrediction.types',
        ]);
    }
}
