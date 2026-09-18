<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereGeocodeMapperTest extends TestCase
{
    private function fixture(string $nome): array
    {
        return json_decode(file_get_contents(__DIR__ . "/../../../Fixtures/here/{$nome}.json"), true);
    }

    public function test_mapeia_endereco_do_geocode(): void
    {
        $resultado = (new HereGeocodeResponseMapper())->toCollection($this->fixture('geocode'))->first();

        $this->assertSame('Avenida Paulista', $resultado->address->street);
        $this->assertSame('1000', $resultado->address->number);
        $this->assertSame('Bela Vista', $resultado->address->neighborhood, 'district vira neighborhood');
        $this->assertSame('Sao Paulo', $resultado->address->city);
        $this->assertSame('SP', $resultado->address->state, 'usa stateCode');
        $this->assertSame('01310100', $resultado->address->postalCode, 'CEP sem hifen');
        $this->assertSame(-23.5615, $resultado->coordinates->latitude);
        $this->assertSame(Provider::Here, $resultado->place->provider);
    }

    public function test_expoe_o_query_score_cru_em_match_score(): void
    {
        $resultado = (new HereGeocodeResponseMapper())->toCollection($this->fixture('geocode'))->first();

        $this->assertSame(0.87, $resultado->matchScore);
    }

    public function test_limiar_default_marca_como_parcial_tudo_que_nao_e_perfeito(): void
    {
        $resultado = (new HereGeocodeResponseMapper())->toCollection($this->fixture('geocode'))->first();

        $this->assertTrue($resultado->partial, 'score 0.87 com limiar 1.0');
    }

    public function test_limiar_configuravel_muda_a_classificacao(): void
    {
        $resultado = (new HereGeocodeResponseMapper(0.8))->toCollection($this->fixture('geocode'))->first();

        $this->assertFalse($resultado->partial, 'score 0.87 com limiar 0.8 e considerado exato');
        $this->assertSame(0.87, $resultado->matchScore, 'o score cru nao muda com o limiar');
    }

    public function test_aceita_objeto_unico_do_lookup(): void
    {
        $colecao = (new HereGeocodeResponseMapper())->toCollection($this->fixture('lookup'));

        $this->assertCount(1, $colecao);
        $this->assertSame('Rua Treze de Maio', $colecao->first()->address->street);
        $this->assertFalse($colecao->first()->partial, 'sem scoring, nao e parcial');
        $this->assertNull($colecao->first()->matchScore, 'o /lookup nao devolve scoring');
    }

    public function test_resposta_sem_itens_devolve_colecao_vazia(): void
    {
        $colecao = (new HereGeocodeResponseMapper())->toCollection(['items' => []]);

        $this->assertTrue($colecao->isEmpty());
    }
}
