<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutosuggestRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutosuggestResponseMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereAutosuggestMapperTest extends TestCase
{
    public function test_monta_query_com_coordenada_e_pais(): void
    {
        $query = (new HereAutosuggestRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), 3000, ['BR']),
            'pt-BR',
            'BR',
        );

        $this->assertSame('Av Paulista', $query['q']);
        $this->assertSame('-23.5000000,-46.6000000', $query['at']);
        $this->assertSame('countryCode:BRA', $query['in']);
        $this->assertSame('pt-BR', $query['lang']);
    }

    public function test_usa_regiao_padrao_quando_nao_ha_pais_no_request(): void
    {
        $query = (new HereAutosuggestRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista'),
            'pt-BR',
            'BR',
        );

        $this->assertSame('countryCode:BRA', $query['in']);
        $this->assertArrayNotHasKey('at', $query);
    }

    public function test_mapeia_itens_e_anula_place_em_chain_query(): void
    {
        $json = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/autosuggest.json'), true);

        $colecao = (new HereAutosuggestResponseMapper())->toCollection($json);

        $this->assertCount(3, $colecao);

        $itens = $colecao->all();

        $this->assertSame(Provider::Here, $itens[0]->place->provider);
        $this->assertFalse($itens[0]->isEstablishment);

        $this->assertTrue($itens[1]->isEstablishment);

        $this->assertNull($itens[2]->place, 'chainQuery nao e resolvivel pelo Lookup');
        $this->assertSame('Postos Shell', $itens[2]->mainText);
    }

    public function test_separa_main_text_de_secondary_text(): void
    {
        $json = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/autosuggest.json'), true);

        $primeiro = (new HereAutosuggestResponseMapper())->toCollection($json)->first();

        $this->assertSame('Avenida Paulista, 1000', $primeiro->mainText);
        $this->assertSame('Sao Paulo - SP, 01310-100, Brasil', $primeiro->secondaryText);
    }

    public function test_converte_pais_fora_das_quatro_entradas_antigas(): void
    {
        $query = (new HereAutosuggestRequestMapper())->toQuery(
            new AutocompleteRequest('Zocalo', null, null, ['MX']),
            'pt-BR',
            'BR',
        );

        $this->assertSame('countryCode:MEX', $query['in']);
    }

    public function test_codigo_alpha3_passa_direto(): void
    {
        $query = (new HereAutosuggestRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['BRA']),
            'pt-BR',
            'BR',
        );

        $this->assertSame('countryCode:BRA', $query['in']);
    }

    public function test_codigo_de_pais_invalido_lanca_excecao(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/XX/');

        (new HereAutosuggestRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['XX']),
            'pt-BR',
            'BR',
        );
    }

    public function test_codigo_de_pais_com_nome_por_extenso_lanca_excecao(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/Brasil/');

        (new HereAutosuggestRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['Brasil']),
            'pt-BR',
            'BR',
        );
    }

    public function test_multiplos_paises_sao_convertidos_e_unidos(): void
    {
        $query = (new HereAutosuggestRequestMapper())->toQuery(
            new AutocompleteRequest('Fronteira', null, null, ['BR', 'MX']),
            'pt-BR',
            'BR',
        );

        $this->assertSame('countryCode:BRA,MEX', $query['in']);
    }
}
