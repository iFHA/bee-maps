<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\Suggestion;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class GoogleAutocompleteResponseMapper
{
    public function toCollection(array $response): SuggestionCollection
    {
        $suggestions = [];

        foreach ($response['suggestions'] ?? [] as $item) {
            $prediction = $item['placePrediction'] ?? null;

            if ($prediction === null || ! isset($prediction['placeId'], $prediction['text']['text'])) {
                continue;
            }

            $suggestions[] = new Suggestion(
                place: new PlaceReference(Provider::Google, $prediction['placeId']),
                description: $prediction['text']['text'],
                mainText: $prediction['structuredFormat']['mainText']['text'] ?? $prediction['text']['text'],
                secondaryText: $prediction['structuredFormat']['secondaryText']['text'] ?? '',
                isEstablishment: in_array('establishment', $prediction['types'] ?? [], true),
            );
        }

        return new SuggestionCollection(...$suggestions);
    }
}
