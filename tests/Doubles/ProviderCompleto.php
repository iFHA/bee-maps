<?php

namespace BeeDelivery\BeeMaps\Tests\Doubles;

use BeeDelivery\BeeMaps\Contracts\Capabilities\ProvidesAutocomplete;
use BeeDelivery\BeeMaps\Contracts\MapProvider;
use BeeDelivery\BeeMaps\Contracts\Services\Autocomplete;
use BeeDelivery\BeeMaps\Enums\Provider;

final class ProviderCompleto implements MapProvider, ProvidesAutocomplete
{
    public function slug(): Provider
    {
        return Provider::Google;
    }

    public function autocomplete(): Autocomplete
    {
        return new AutocompleteFalso();
    }
}
