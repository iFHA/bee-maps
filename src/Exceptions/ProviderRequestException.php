<?php

namespace BeeDelivery\BeeMaps\Exceptions;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;

class ProviderRequestException extends BeeMapsException
{
    public function __construct(
        private readonly Provider $provider,
        private readonly Service $service,
        string $message,
        private readonly ?int $httpStatus = null,
        private readonly ?string $providerCode = null,
    ) {
        parent::__construct(sprintf('[%s/%s] %s', $provider->value, $service->value, $message));
    }

    public function provider(): Provider
    {
        return $this->provider;
    }

    public function service(): Service
    {
        return $this->service;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function providerCode(): ?string
    {
        return $this->providerCode;
    }
}
