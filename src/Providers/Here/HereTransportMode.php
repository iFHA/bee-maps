<?php

namespace BeeDelivery\BeeMaps\Providers\Here;

use BeeDelivery\BeeMaps\Enums\TravelMode;

/**
 * Mesma tabela para /v8/routes, /v8/matrix e findsequence2 — os tres endpoints
 * do HERE usam os mesmos nomes de modo. Estava duplicada em tres mappers.
 */
final class HereTransportMode
{
    public static function from(TravelMode $modo): string
    {
        return match ($modo) {
            TravelMode::Drive => 'car',
            TravelMode::TwoWheeler => 'scooter',
            TravelMode::Bicycle => 'bicycle',
            TravelMode::Walk => 'pedestrian',
        };
    }
}
