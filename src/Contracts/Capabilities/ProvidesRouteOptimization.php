<?php

namespace BeeDelivery\BeeMaps\Contracts\Capabilities;

use BeeDelivery\BeeMaps\Contracts\Services\RouteOptimization;

interface ProvidesRouteOptimization
{
    public function routeOptimization(): RouteOptimization;
}
