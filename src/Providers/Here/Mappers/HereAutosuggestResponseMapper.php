<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Responses\Suggestion;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Providers\Here\HereAddressLabel;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final class HereAutosuggestResponseMapper
{
    /** resultType que sao refinamentos de busca, nao lugares: nao resolvem no /lookup. */
    private const NAO_RESOLVIVEIS = ['chainQuery', 'categoryQuery'];

    /**
     * Tipos cujo `title` e um nome proprio, e nao um endereco: o nome do lugar
     * ("Shopping Ibirapuera") ou o rotulo da busca ("Postos Shell"). Para todo
     * o resto o `title` do Autosuggest vem IGUAL ao `address.label` completo,
     * entao a linha principal tem que ser derivada do endereco estruturado.
     */
    private const TITULO_E_NOME = ['place', ...self::NAO_RESOLVIVEIS];

    public function toCollection(array $resposta): SuggestionCollection
    {
        $sugestoes = [];

        foreach ($resposta['items'] ?? [] as $item) {
            $titulo = $item['title'] ?? null;

            if ($titulo === null) {
                continue;
            }

            $tipo = $item['resultType'] ?? '';
            $label = $item['address']['label'] ?? $titulo;
            $resolvivel = ! in_array($tipo, self::NAO_RESOLVIVEIS, true) && isset($item['id']);

            $principal = in_array($tipo, self::TITULO_E_NOME, true)
                ? $titulo
                : HereAddressLabel::principal($item['address'] ?? [], $label);

            $sugestoes[] = new Suggestion(
                place: $resolvivel ? new PlaceReference(Provider::Here, $item['id']) : null,
                description: $label,
                mainText: $principal,
                secondaryText: HereAddressLabel::complemento($principal, $label),
                isEstablishment: $tipo === 'place',
            );
        }

        return new SuggestionCollection(...$sugestoes);
    }
}
