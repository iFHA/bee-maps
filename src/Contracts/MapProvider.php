<?php

namespace BeeDelivery\BeeMaps\Contracts;

use BeeDelivery\BeeMaps\Enums\Provider;

interface MapProvider
{
    public function slug(): Provider;
}
