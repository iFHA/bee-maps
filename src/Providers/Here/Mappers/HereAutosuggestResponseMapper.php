<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\Suggestion;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Providers\Here\HereAddressLabel;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class HereAutosuggestResponseMapper
{
    /** resultType que sao refinamentos de busca, nao lugares: nao resolvem no /lookup. */
    private const NOT_RESOLVABLE = ['chainQuery', 'categoryQuery'];

    /**
     * Tipos cujo `title` e um nome proprio, e nao um endereco: o nome do lugar
     * ("Shopping Ibirapuera") ou o rotulo da busca ("Postos Shell"). Para todo
     * o resto o `title` do Autosuggest vem IGUAL ao `address.label` completo,
     * entao a linha principal tem que ser derivada do endereco estruturado.
     */
    private const TITLE_IS_NAME = ['place', ...self::NOT_RESOLVABLE];

    public function toCollection(array $response): SuggestionCollection
    {
        $suggestions = [];

        foreach ($response['items'] ?? [] as $item) {
            $title = $item['title'] ?? null;

            if ($title === null) {
                continue;
            }

            $type = $item['resultType'] ?? '';
            $label = $item['address']['label'] ?? $title;
            $resolvable = ! in_array($type, self::NOT_RESOLVABLE, true) && isset($item['id']);

            $main = in_array($type, self::TITLE_IS_NAME, true)
                ? $title
                : HereAddressLabel::mainText($item['address'] ?? [], $label);

            $suggestions[] = new Suggestion(
                place: $resolvable ? new PlaceReference(Provider::Here, $item['id']) : null,
                description: $label,
                mainText: $main,
                secondaryText: HereAddressLabel::secondaryText($main, $label),
                isEstablishment: $type === 'place',
                // Este endpoint ja devolve a posicao do item, entao o consumidor
                // nao precisa de um geocode depois da escolha. O irmao
                // /autocomplete nao devolve — ver HereAutocompleteResponseMapper.
                coordinates: $this->position($item),
            );
        }

        return new SuggestionCollection(...$suggestions);
    }

    /**
     * O `position` falta nos itens que nao sao lugares (`chainQuery`,
     * `categoryQuery`): sao refinamentos de busca, e um refinamento nao tem
     * onde ficar no mapa. Sao os mesmos itens em que `place` ja e nulo.
     */
    private function position(array $item): ?Coordinates
    {
        if (! isset($item['position']['lat'], $item['position']['lng'])) {
            return null;
        }

        return new Coordinates((float) $item['position']['lat'], (float) $item['position']['lng']);
    }
}
