<?php

namespace BeeDelivery\BeeMaps\Tests\Doubles;

use BeeDelivery\BeeMaps\Providers\Google\Optimization\AccessToken;

final class TokenFalso implements AccessToken
{
    public function __construct(private readonly string $token = 'token-de-teste')
    {
    }

    public function valor(): string
    {
        return $this->token;
    }
}
