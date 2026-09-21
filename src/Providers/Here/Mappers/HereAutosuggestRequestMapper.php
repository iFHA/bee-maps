<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Support\CountryCode;

final class HereAutosuggestRequestMapper
{
    public function toQuery(AutocompleteRequest $request, string $language, string $region): array
    {
        $query = [
            'q' => $request->query,
            'lang' => $request->language ?? $language,
        ];

        $filtrosIn = [];

        if ($request->near !== null) {
            $query['at'] = $request->near->toString();

            if ($request->radiusMeters !== null) {
                $filtrosIn[] = sprintf('circle:%s;r=%d', $request->near->toString(), $request->radiusMeters);
            }
        }

        $paises = $request->countries !== [] ? $request->countries : [$region];
        $codigos = array_map(fn (string $p) => CountryCode::toAlpha3($p), $paises);

        $filtrosIn[] = 'countryCode:' . implode(',', $codigos);

        $query['in'] = $filtrosIn;

        return $query;
    }
}
