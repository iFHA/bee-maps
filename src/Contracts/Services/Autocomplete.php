<?php

namespace BeeDelivery\BeeMaps\Contracts\Services;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;

interface Autocomplete
{
    public function suggest(AutocompleteRequest $request): SuggestionCollection;
}
