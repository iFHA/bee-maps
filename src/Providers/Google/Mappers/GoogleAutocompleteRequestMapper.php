<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Support\CountryCode;

final class GoogleAutocompleteRequestMapper
{
    public function toPayload(AutocompleteRequest $request, string $language, string $region): array
    {
        $payload = [
            'input' => $request->query,
            'languageCode' => $request->language ?? $language,
        ];

        if ($request->near !== null) {
            $circle = [
                'center' => [
                    'latitude' => $request->near->latitude,
                    'longitude' => $request->near->longitude,
                ],
            ];

            if ($request->radiusMeters !== null) {
                $circle['radius'] = $request->radiusMeters;
            }

            $payload['locationRestriction'] = ['circle' => $circle];
        }

        $countries = $request->countries !== [] ? $request->countries : [$region];
        $payload['includedRegionCodes'] = array_map(fn (string $p) => CountryCode::toAlpha2($p), $countries);

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
