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

            $sugestoes[] = new Suggestion(
                place: $resolvivel ? new PlaceReference(Provider::Here, $item['id']) : null,
                description: $label,
                mainText: $titulo,
                secondaryText: HereAddressLabel::complemento($titulo, $label),
                isEstablishment: $tipo === 'place',
            );
        }

        return new SuggestionCollection(...$sugestoes);
    }
}
