<?php

namespace BeeDelivery\BeeMaps\Contracts\Capabilities;

use BeeDelivery\BeeMaps\Contracts\Services\Geocoding;

interface ProvidesGeocoding
{
    public function geocoding(): Geocoding;
}
