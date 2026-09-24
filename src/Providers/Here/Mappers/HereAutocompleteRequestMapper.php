<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Support\CountryCode;

/**
 * Mapper do endpoint /autocomplete do HERE — o irmao do
 * HereAutosuggestRequestMapper, e a razao de existirem dois.
 *
 * Aqui `q` e o unico parametro obrigatorio: `at`, `in=circle` e `in=bbox` sao
 * todos opcionais e `in=countryCode` vale sozinho. E o que torna este o unico
 * endpoint de busca do GS7 capaz de responder "no pais inteiro".
 *
 * O que continua valendo do /autosuggest e a exclusao mutua: `at` e `in=circle`
 * juntos sao 400 ("Parameters 'at', 'in=circle' and 'in=bbox' are mutually
 * exclusive"), entao o foco, quando existe, sai por exatamente um dos dois.
 */
final class HereAutocompleteRequestMapper
{
    public function toQuery(AutocompleteRequest $request, string $language, string $region): array
    {
        $query = [
            'q' => $request->query,
            'lang' => $request->language ?? $language,
        ];

        $paises = $request->countries !== [] ? $request->countries : [$region];
        $codigos = array_map(fn (string $p) => CountryCode::toAlpha3($p), $paises);

        $filtrosIn = [];

        // Sem `near` nao se inventa foco: a ausencia dele e o pedido de busca
        // nacional, e o filtro de pais abaixo da conta sozinho.
        if ($request->near !== null) {
            if ($request->radiusMeters !== null) {
                $filtrosIn[] = sprintf('circle:%s;r=%d', $request->near->toString(), $request->radiusMeters);
            } else {
                $query['at'] = $request->near->toString();
            }
        }

        $filtrosIn[] = 'countryCode:' . implode(',', $codigos);

        $query['in'] = $filtrosIn;

        return $query;
    }
}
