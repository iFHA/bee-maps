<?php

namespace BeeDelivery\BeeMaps\Tests\Doubles;

use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;

final class AutocompleteFalso implements Autocomplete
{
    public function suggest(AutocompleteRequest $request): SuggestionCollection
    {
        return new SuggestionCollection();
    }
}
