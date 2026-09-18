<?php

namespace BeeDelivery\BeeMaps\Exceptions;

use BeeDelivery\BeeMaps\Enums\Provider;

final class ProviderNotSupportedException extends ConfigurationException
{
    public static function make(Provider $provider): self
    {
        return new self(sprintf(
            'O provider "%s" nao esta registrado em bee-maps.providers.',
            $provider->value,
        ));
    }
}
