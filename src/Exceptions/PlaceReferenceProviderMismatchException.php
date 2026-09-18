<?php

namespace BeeDelivery\BeeMaps\Exceptions;

use BeeDelivery\BeeMaps\Enums\Provider;

final class PlaceReferenceProviderMismatchException extends InvalidRequestException
{
    public static function make(Provider $esperado, Provider $recebido): self
    {
        return new self(sprintf(
            'Referencia de lugar emitida por "%s" nao pode ser consultada em "%s". Refaca a busca por texto.',
            $recebido->value,
            $esperado->value,
        ));
    }
}
