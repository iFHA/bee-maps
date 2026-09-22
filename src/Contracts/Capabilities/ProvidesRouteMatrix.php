<?php

namespace BeeDelivery\BeeMaps\Contracts\Capabilities;

use BeeDelivery\BeeMaps\Contracts\Services\RouteMatrix;

interface ProvidesRouteMatrix
{
    public function routeMatrix(): RouteMatrix;
}
