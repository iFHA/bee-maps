<?php

namespace BeeDelivery\BeeMaps\Exceptions;

use BeeDelivery\BeeMaps\Enums\Provider;

final class MissingCredentialsException extends ConfigurationException
{
    public static function make(Provider $provider, string $configKey): self
    {
        return new self(sprintf(
            'Credencial do provider "%s" ausente. Defina %s.',
            $provider->value,
            $configKey,
        ));
    }
}
