<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\Suggestion;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class GoogleAutocompleteResponseMapper
{
    public function toCollection(array $resposta): SuggestionCollection
    {
        $sugestoes = [];

        foreach ($resposta['suggestions'] ?? [] as $item) {
            $predicao = $item['placePrediction'] ?? null;

            if ($predicao === null || ! isset($predicao['placeId'], $predicao['text']['text'])) {
                continue;
            }

            $sugestoes[] = new Suggestion(
                place: new PlaceReference(Provider::Google, $predicao['placeId']),
                description: $predicao['text']['text'],
                mainText: $predicao['structuredFormat']['mainText']['text'] ?? $predicao['text']['text'],
                secondaryText: $predicao['structuredFormat']['secondaryText']['text'] ?? '',
                isEstablishment: in_array('establishment', $predicao['types'] ?? [], true),
            );
        }

        return new SuggestionCollection(...$sugestoes);
    }
}
