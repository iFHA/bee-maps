<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Services;

use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Providers\Here\HereAutocompleteStrategy;

/**
 * O autocomplete do HERE tem dois endpoints com capacidades diferentes, e a
 * escolha entre eles depende da requisicao, nao so do config — dai o despacho
 * ficar aqui e nao no HereProvider, que monta os servicos antes de ver a
 * primeira chamada. Ver HereAutocompleteStrategy para o porque de cada rota.
 */
final class HereAutocomplete implements Autocomplete
{
    public function __construct(
        private readonly HereAutocompleteStrategy $strategy,
        private readonly Autocomplete $autosuggest,
        private readonly Autocomplete $autocomplete,
    ) {
    }

    public function suggest(AutocompleteRequest $request): SuggestionCollection
    {
        return $this->strategy->resolve($request) === HereAutocompleteStrategy::Autosuggest
            ? $this->autosuggest->suggest($request)
            : $this->autocomplete->suggest($request);
    }
}
