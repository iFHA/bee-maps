<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\DTOs\TypedCollection;

final class PlaceCollection extends TypedCollection
{
    public function __construct(Place ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @return list<Place>
     */
    public function all(): array
    {
        return parent::all();
    }

    public function first(): ?Place
    {
        return $this->items[0] ?? null;
    }
}
