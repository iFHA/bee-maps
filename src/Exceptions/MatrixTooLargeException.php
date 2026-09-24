<?php

namespace BeeDelivery\BeeMaps\Exceptions;

use BeeDelivery\BeeMaps\Enums\Provider;

class MatrixTooLargeException extends InvalidRequestException
{
    public static function make(Provider $provider, int $elements, int $limit): self
    {
        return new self(sprintf(
            'Matriz de %d elementos excede o limite de %d do provider %s. '
            . 'Divida a requisicao em lotes menores.',
            $elements,
            $limit,
            $provider->value,
        ));
    }
}
