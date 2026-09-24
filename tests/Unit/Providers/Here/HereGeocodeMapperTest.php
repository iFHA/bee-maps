<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereGeocodeMapperTest extends TestCase
{
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__ . "/../../../Fixtures/here/{$name}.json"), true);
    }

    public function test_mapeia_endereco_do_geocode(): void
    {
        $result = (new HereGeocodeResponseMapper())->toCollection($this->fixture('geocode'))->first();

        $this->assertSame('Avenida Paulista', $result->address->street);
        $this->assertSame('1000', $result->address->number);
        $this->assertSame('Bela Vista', $result->address->neighborhood, 'district vira neighborhood');
        $this->assertSame('Sao Paulo', $result->address->city);
        $this->assertSame('SP', $result->address->state, 'usa stateCode');
        $this->assertSame('01310100', $result->address->postalCode, 'CEP sem hifen');
        $this->assertSame(-23.5615, $result->coordinates->latitude);
        $this->assertSame(Provider::Here, $result->place->provider);
    }

    public function test_expoe_o_query_score_cru_em_match_score(): void
    {
        $result = (new HereGeocodeResponseMapper())->toCollection($this->fixture('geocode'))->first();

        $this->assertSame(0.87, $result->matchScore);
    }

    public function test_limiar_default_marca_como_parcial_tudo_que_nao_e_perfeito(): void
    {
        $result = (new HereGeocodeResponseMapper())->toCollection($this->fixture('geocode'))->first();

        $this->assertTrue($result->partial, 'score 0.87 com limiar 1.0');
    }

    public function test_limiar_configuravel_muda_a_classificacao(): void
    {
        $result = (new HereGeocodeResponseMapper(0.8))->toCollection($this->fixture('geocode'))->first();

        $this->assertFalse($result->partial, 'score 0.87 com limiar 0.8 e considerado exato');
        $this->assertSame(0.87, $result->matchScore, 'o score cru nao muda com o limiar');
    }

    public function test_aceita_objeto_unico_do_lookup(): void
    {
        $collection = (new HereGeocodeResponseMapper())->toCollection($this->fixture('lookup'));

        $this->assertCount(1, $collection);
        $this->assertSame('Rua Treze de Maio', $collection->first()->address->street);
        $this->assertFalse($collection->first()->partial, 'sem scoring, nao e parcial');
        $this->assertNull($collection->first()->matchScore, 'o /lookup nao devolve scoring');
    }

    public function test_resposta_sem_itens_devolve_colecao_vazia(): void
    {
        $collection = (new HereGeocodeResponseMapper())->toCollection(['items' => []]);

        $this->assertTrue($collection->isEmpty());
    }
}
