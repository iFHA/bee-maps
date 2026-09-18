<?php

namespace BeeDelivery\BeeMaps\Tests\Doubles;

use BeeDelivery\BeeMaps\Contracts\MapProvider;
use BeeDelivery\BeeMaps\Enums\Provider;

final class ProviderSemCapacidades implements MapProvider
{
    public function slug(): Provider
    {
        return Provider::Google;
    }
}
