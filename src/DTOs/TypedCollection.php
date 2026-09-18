<?php

namespace BeeDelivery\BeeMaps\DTOs;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

abstract class TypedCollection implements IteratorAggregate, Countable
{
    protected array $items = [];

    public function all(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
