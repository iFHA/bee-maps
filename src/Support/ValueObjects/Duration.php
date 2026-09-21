<?php

namespace BeeDelivery\BeeMaps\Support\ValueObjects;

use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;

final readonly class Duration
{
    public function __construct(public int $seconds)
    {
        if ($seconds < 0) {
            throw new InvalidRequestException("Duracao nao pode ser negativa: {$seconds}.");
        }
    }

    public function minutes(): float
    {
        return $this->seconds / 60;
    }
}
