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
                // Sempre nulo, e nao e omissao do field mask: o
                // `placePrediction` do places:autocomplete nao tem campo de
                // localizacao nenhum — so `place`, `placeId`, `text`,
                // `structuredFormat`, `types` e `distanceMeters`. A coordenada
                // no Google so vem do Place Details, que e outra chamada e
                // outro SKU. O HERE devolve no /autosuggest; ver a nota de
                // paridade no README.
                coordinates: null,
            );
        }

        return new SuggestionCollection(...$suggestions);
    }
}
