<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Optimization;

/**
 * Existe para que a FleetRoutingStrategy seja testavel sem service account: o
 * unico ponto do pacote que precisa de OAuth fica atras desta interface.
 */
interface AccessToken
{
    public function value(): string;
}
