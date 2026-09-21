<?php

namespace BeeDelivery\BeeMaps\Contracts\Capabilities;

use BeeDelivery\BeeMaps\Contracts\Services\Routing;

interface ProvidesRouting
{
    public function routing(): Routing;
}
