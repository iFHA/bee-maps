<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Support\WaypointOrder;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class WaypointOrderTest extends TestCase
{
    public function test_permutacao_completa_passa(): void
    {
        $this->assertSame([2, 0, 1], WaypointOrder::validate([2, 0, 1], 3, Provider::Google, 'teste'));
    }

    public function test_ordem_curta_e_recusada(): void
    {
        // Ordem parcial e pior que erro: o chamador usa $order para reordenar a
        // propria lista de entregas, e uma ordem com buraco apaga paradas.
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/2 de 3/');

        WaypointOrder::validate([0, 1], 3, Provider::Google, 'teste');
    }

    public function test_indice_repetido_e_recusado(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/repetid/i');

        WaypointOrder::validate([0, 1, 1], 3, Provider::Google, 'teste');
    }

    public function test_indice_fora_da_faixa_e_recusado(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/fora da faixa/i');

        WaypointOrder::validate([0, 1, 7], 3, Provider::Google, 'teste');
    }

    public function test_contagem_certa_com_indice_fora_da_faixa_nao_passa(): void
    {
        // A guarda de contagem sozinha aprovaria: 3 elementos para 3 esperados.
        // O invariante e "permutacao de 0..N-1", nao "tem N elementos".
        $this->expectException(ProviderRequestException::class);

        WaypointOrder::validate([0, 1, 3], 3, Provider::Google, 'teste');
    }
}
