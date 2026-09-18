<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;

final class HereAutosuggestRequestMapper
{
    /** ISO 3166-1 alpha-2 -> alpha-3, que e o formato aceito pelo filtro "in" do HERE. */
    private const ALPHA3 = ['BR' => 'BRA', 'AR' => 'ARG', 'US' => 'USA', 'PT' => 'PRT'];

    public function toQuery(AutocompleteRequest $request, string $language, string $region): array
    {
        $query = [
            'q' => $request->query,
            'lang' => $request->language ?? $language,
        ];

        if ($request->near !== null) {
            $query['at'] = $request->near->toString();
        }

        $paises = $request->countries !== [] ? $request->countries : [$region];
        $codigos = array_map(fn (string $p) => self::ALPHA3[strtoupper($p)] ?? strtoupper($p), $paises);

        $query['in'] = 'countryCode:' . implode(',', $codigos);

        return $query;
    }
}
