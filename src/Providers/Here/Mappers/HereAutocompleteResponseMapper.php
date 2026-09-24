<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\Suggestion;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Providers\Here\HereAddressLabel;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

/**
 * O /autocomplete devolve `title` em ordem INVERTIDA, comecando pelo pais
 * ("Brasil, Sao Paulo - SP, 01310-100, Avenida Paulista, 1000"). Esse formato
 * so existe neste endpoint e serve para destacar os termos digitados; usa-lo
 * como `mainText` quebraria o consumidor. Quem segue a ordem postal e o
 * `address.label`, e e dele que saem os tres campos de texto — assim o formato
 * bate com o do Google e com o do /autosuggest.
 */
final class HereAutocompleteResponseMapper
{
    public function toCollection(array $response): SuggestionCollection
    {
        $suggestions = [];

        foreach ($response['items'] ?? [] as $item) {
            $label = $item['address']['label'] ?? $item['title'] ?? null;

            if ($label === null) {
                continue;
            }

            $main = HereAddressLabel::mainText($item['address'] ?? [], $label);

            $suggestions[] = new Suggestion(
                // Diferente do /autosuggest, aqui todo item e resolvivel no
                // /lookup: nao existem `chainQuery`/`categoryQuery` neste
                // endpoint. O isset e defesa contra resposta malformada.
                place: isset($item['id']) ? new PlaceReference(Provider::Here, $item['id']) : null,
                description: $label,
                mainText: $main,
                secondaryText: HereAddressLabel::secondaryText($main, $label),
                // O `resultType` do /autocomplete vai de `administrativeArea` a
                // `street`: e um enum fechado, sem `place`. Nunca ha
                // estabelecimento aqui — ver "Limitacoes conhecidas" no README.
                isEstablishment: false,
                // Sempre nulo, e nao ha parametro que mude isso: a propria doc
                // do /autocomplete manda resolver a posicao depois, via /lookup
                // pelo `id` ou via /geocode pelo endereco. A tabela de response
                // enrichment do GS7 nao tem nenhum `show` que adicione
                // `position` aqui — `tz`, por exemplo, vale em "all except
                // /autocomplete". Quem precisa da coordenada sem pagar a
                // chamada extra tem que buscar com `near`, que roteia para o
                // /autosuggest.
                coordinates: null,
            );
        }

        return new SuggestionCollection(...$suggestions);
    }
}
