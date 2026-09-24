<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\DTOs\TypedCollection;

final class RouteMatrixEntryCollection extends TypedCollection
{
    /** @var array<string, RouteMatrixEntry> */
    private array $byPair = [];

    public function __construct(RouteMatrixEntry ...$items)
    {
        $this->items = array_values($items);

        foreach ($this->items as $entry) {
            $this->byPair[$entry->originIndex . ':' . $entry->destinationIndex] = $entry;
        }
    }

    /**
     * @return list<RouteMatrixEntry>
     */
    public function all(): array
    {
        return parent::all();
    }

    public function first(): ?RouteMatrixEntry
    {
        return $this->items[0] ?? null;
    }

    /**
     * Busca por par, nao por posicao: o Google devolve os elementos fora de ordem
     * (verificado: numa matriz 2x2 real, (0,1) veio antes de (0,0)) e o HERE
     * devolve arrays achatados. Indexar por posicao embaralha a matriz em silencio.
     */
    public function entry(int $originIndex, int $destinationIndex): ?RouteMatrixEntry
    {
        return $this->byPair[$originIndex . ':' . $destinationIndex] ?? null;
    }
}
