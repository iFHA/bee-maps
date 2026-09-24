<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Support\WaypointOrder;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class WaypointOrderTest extends TestCase
{
    public function test_a_complete_permutation_passes(): void
    {
        $this->assertSame([2, 0, 1], WaypointOrder::validate([2, 0, 1], 3, Provider::Google, 'teste'));
    }

    public function test_a_short_order_is_refused(): void
    {
        // Ordem parcial e pior que erro: o chamador usa $order para reordenar a
        // propria lista de entregas, e uma ordem com buraco apaga paradas.
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/2 de 3/');

        WaypointOrder::validate([0, 1], 3, Provider::Google, 'teste');
    }

    public function test_a_repeated_index_is_refused(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/repetid/i');

        WaypointOrder::validate([0, 1, 1], 3, Provider::Google, 'teste');
    }

    public function test_an_out_of_range_index_is_refused(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/fora da faixa/i');

        WaypointOrder::validate([0, 1, 7], 3, Provider::Google, 'teste');
    }

    public function test_the_right_count_with_an_out_of_range_index_does_not_pass(): void
    {
        // A guarda de contagem sozinha aprovaria: 3 elementos para 3 esperados.
        // O invariante e "permutacao de 0..N-1", nao "tem N elementos".
        $this->expectException(ProviderRequestException::class);

        WaypointOrder::validate([0, 1, 3], 3, Provider::Google, 'teste');
    }
}
