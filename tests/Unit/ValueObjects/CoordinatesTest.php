<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\ValueObjects;

use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class CoordinatesTest extends TestCase
{
    public function test_formata_como_lat_lng(): void
    {
        $this->assertSame('-23.5505,-46.6333', (new Coordinates(-23.5505, -46.6333))->toString());
    }

    public function test_rejeita_latitude_fora_do_intervalo(): void
    {
        $this->expectException(InvalidRequestException::class);

        new Coordinates(91.0, 0.0);
    }

    public function test_rejeita_longitude_fora_do_intervalo(): void
    {
        $this->expectException(InvalidRequestException::class);

        new Coordinates(0.0, 181.0);
    }
}
