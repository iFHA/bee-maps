<?php

namespace BeeDelivery\BeeMaps\Exceptions;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;

final class ServiceNotSupportedByProviderException extends ConfigurationException
{
    public static function make(Provider $provider, Service $service): self
    {
        return new self(sprintf(
            'O provider "%s" nao implementa o servico "%s".',
            $provider->value,
            $service->value,
        ));
    }
}
