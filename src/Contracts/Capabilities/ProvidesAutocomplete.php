<?php

namespace BeeDelivery\BeeMaps\Contracts\Capabilities;

use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;

interface ProvidesAutocomplete
{
    public function autocomplete(): Autocomplete;
}
