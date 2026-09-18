<?php

namespace BeeDelivery\BeeMaps\Contracts\Services;

use BeeDelivery\BeeMaps\DTOs\Requests\GeocodeFilters;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResult;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResultCollection;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

interface Geocoding
{
    public function geocode(string $query, ?GeocodeFilters $filters = null): GeocodeResultCollection;

    public function reverse(Coordinates $coordinates): GeocodeResultCollection;

    /**
     * Devolve null quando o identificador nao existe no provider.
     *
     * @throws \BeeDelivery\BeeMaps\Exceptions\PlaceReferenceProviderMismatchException
     *         quando a referencia foi emitida por outro provider
     */
    public function lookup(PlaceReference $place): ?GeocodeResult;
}
