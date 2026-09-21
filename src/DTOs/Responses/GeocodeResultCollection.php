<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\DTOs\TypedCollection;

final class GeocodeResultCollection extends TypedCollection
{
    public function __construct(GeocodeResult ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @return list<GeocodeResult>
     */
    public function all(): array
    {
        return parent::all();
    }

    /**
     * @return GeocodeResult|null
     */
    public function first(): ?GeocodeResult
    {
        return $this->items[0] ?? null;
    }
}
