<?php

namespace BeeDelivery\BeeMaps\Tests\Unit;

use BeeDelivery\BeeMaps\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function test_registra_o_config_do_pacote(): void
    {
        $this->assertSame('pt-BR', config('bee-maps.defaults.language'));
        $this->assertSame(10, config('bee-maps.http.timeout'));
    }
}
