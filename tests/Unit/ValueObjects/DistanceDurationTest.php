<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\ValueObjects;

use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class DistanceDurationTest extends TestCase
{
    public function test_distancia_converte_metros_em_quilometros(): void
    {
        $this->assertSame(1.5, (new Distance(1500))->kilometers());
        $this->assertSame(0.0, (new Distance(0))->kilometers());
    }

    public function test_duracao_converte_segundos_em_minutos(): void
    {
        $this->assertSame(2.5, (new Duration(150))->minutes());
        $this->assertSame(0.0, (new Duration(0))->minutes());
    }

    public function test_distancia_negativa_e_rejeitada(): void
    {
        $this->expectException(InvalidRequestException::class);

        new Distance(-1);
    }

    public function test_duracao_negativa_e_rejeitada(): void
    {
        $this->expectException(InvalidRequestException::class);

        new Duration(-1);
    }
}
