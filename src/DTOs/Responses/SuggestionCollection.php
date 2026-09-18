<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\DTOs\TypedCollection;

final class SuggestionCollection extends TypedCollection
{
    public function __construct(Suggestion ...$items)
    {
        $this->items = array_values($items);
    }

    public function first(): ?Suggestion
    {
        return $this->items[0] ?? null;
    }
}
