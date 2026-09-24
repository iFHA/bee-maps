<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\ValueObjects;

use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class DistanceDurationTest extends TestCase
{
    public function test_distance_converts_meters_to_kilometers(): void
    {
        $this->assertSame(1.5, (new Distance(1500))->kilometers());
        $this->assertSame(0.0, (new Distance(0))->kilometers());
    }

    public function test_duration_converts_seconds_to_minutes(): void
    {
        $this->assertSame(2.5, (new Duration(150))->minutes());
        $this->assertSame(0.0, (new Duration(0))->minutes());
    }

    public function test_a_negative_distance_is_rejected(): void
    {
        $this->expectException(InvalidRequestException::class);

        new Distance(-1);
    }

    public function test_a_negative_duration_is_rejected(): void
    {
        $this->expectException(InvalidRequestException::class);

        new Duration(-1);
    }
}
