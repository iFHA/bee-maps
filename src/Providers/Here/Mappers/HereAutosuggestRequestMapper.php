<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Mappers;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\CountryCode;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

final class HereAutosuggestRequestMapper
{
    /**
     * @param Coordinates|null $defaultCenter Foco usado quando a requisicao nao traz
     *                                       coordenada. Vem de bee-maps.here.autosuggest_center.
     */
    public function __construct(private readonly ?Coordinates $defaultCenter = null)
    {
    }

    /**
     * O Autosuggest exige EXATAMENTE UM foco espacial entre `at`, `in=bbox`,
     * `in=circle` e `in=ring`. Dois erros distintos da API delimitam o que pode:
     *
     *   nenhum  -> 400 "Required parameter missing. One of mutual exclusive
     *                   parameters 'at', 'in=bbox', 'in=circle', 'in=ring'
     *                   needs to be present"
     *   dois    -> 400 "Mutually exclusive parameters violated. Only one of
     *                   'at', 'in=bbox', 'in=circle', 'in=ring' is allowed"
     *
     * `in=countryCode` e refinamento: acompanha qualquer um dos dois, mas nao
     * substitui nenhum. Dai a escolha explicita entre `at` e `in=circle`.
     */
    public function toQuery(AutocompleteRequest $request, string $language, string $region): array
    {
        $query = [
            'q' => $request->query,
            'lang' => $request->language ?? $language,
            // Sem `show=details` o `address` do Autosuggest vem so com `label`,
            // e nao ha como derivar a linha principal do endereco — o `title`
            // nao serve, porque para resultado de endereco ele vem igual ao
            // label inteiro. A doc avisa que `show` pode custar latencia; e o
            // preco de `mainText` e `secondaryText` corretos.
            'show' => 'details',
        ];

        // Antes do foco: um codigo de pais invalido deve falhar dizendo isso, e
        // nao ser mascarado por uma mensagem sobre coordenada faltando.
        $countries = $request->countries !== [] ? $request->countries : [$region];
        $codes = array_map(fn (string $p) => CountryCode::toAlpha3($p), $countries);

        $focus = $request->near ?? $this->defaultCenter;

        if ($focus === null) {
            throw new InvalidRequestException(
                'O Autosuggest do HERE exige um foco espacial: informe AutocompleteRequest::$near '
                . 'ou configure bee-maps.here.autosuggest_center (formato "latitude,longitude").',
            );
        }

        $inFilters = [];

        if ($request->radiusMeters !== null) {
            $inFilters[] = sprintf('circle:%s;r=%d', $focus->toString(), $request->radiusMeters);
        } else {
            $query['at'] = $focus->toString();
        }

        $inFilters[] = 'countryCode:' . implode(',', $codes);

        $query['in'] = $inFilters;

        return $query;
    }
}
