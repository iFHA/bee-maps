<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\DTOs\Requests\PlaceSearchRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereDiscoverRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereDiscoverResponseMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereDiscoverMapperTest extends TestCase
{
    public function test_sem_coordenada_usa_a_regiao_do_config_como_filtro_in(): void
    {
        $query = (new HereDiscoverRequestMapper())
            ->toQuery(new PlaceSearchRequest('farmacia'), 'pt-BR', 'BR');

        $this->assertSame('farmacia', $query['q']);
        $this->assertSame('pt-BR', $query['lang']);
        $this->assertSame('countryCode:BRA', $query['in']);
        $this->assertArrayNotHasKey('at', $query);
    }

    public function test_coordenada_vira_at_e_dispensa_o_filtro_in(): void
    {
        $query = (new HereDiscoverRequestMapper())
            ->toQuery(new PlaceSearchRequest('farmacia', new Coordinates(-23.5, -46.6)), 'pt-BR', 'BR');

        $this->assertSame('-23.5000000,-46.6000000', $query['at']);
        $this->assertArrayNotHasKey('in', $query);
    }

    public function test_sem_coordenada_e_sem_regiao_lanca_excecao_dizendo_o_que_faltou(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/at|regi/i');

        (new HereDiscoverRequestMapper())->toQuery(new PlaceSearchRequest('farmacia'), 'pt-BR', '');
    }

    public function test_resposta_vira_colecao_tipada(): void
    {
        $resposta = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/discover.json'), true);

        $colecao = (new HereDiscoverResponseMapper())->toCollection($resposta);

        $this->assertCount(2, $colecao);

        $primeiro = $colecao->first();
        $this->assertSame('Drogaria Sao Paulo', $primeiro->name);
        $this->assertSame(Provider::Here, $primeiro->place->provider);
        $this->assertSame('here:pds:place:076c3nbf-1234567890abcdef', $primeiro->place->id);
        $this->assertSame('Avenida Paulista', $primeiro->address->street);
        $this->assertSame('1000', $primeiro->address->number);
        $this->assertSame('Bela Vista', $primeiro->address->neighborhood);
        $this->assertSame('Sao Paulo', $primeiro->address->city);
        $this->assertSame('SP', $primeiro->address->state);
        $this->assertSame('Brasil', $primeiro->address->country);
        $this->assertSame('01310100', $primeiro->address->postalCode);
        $this->assertEqualsWithDelta(-23.5615, $primeiro->coordinates->latitude, 0.0001);

        $this->assertNull($colecao->all()[1]->address->postalCode);
    }

    public function test_resposta_sem_items_vira_colecao_vazia(): void
    {
        $this->assertTrue((new HereDiscoverResponseMapper())->toCollection([])->isEmpty());
    }
}
