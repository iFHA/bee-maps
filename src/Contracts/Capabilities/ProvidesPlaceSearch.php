<?php

namespace BeeDelivery\BeeMaps\Contracts\Capabilities;

use BeeDelivery\BeeMaps\Contracts\Services\PlaceSearch;

interface ProvidesPlaceSearch
{
    public function placeSearch(): PlaceSearch;
}
