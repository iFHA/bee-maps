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
    private const CENTER = '-23.5615000,-46.6562000';

    private function fixture(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/autosuggest.json'), true);
    }

    private function withCenter(): HereAutosuggestRequestMapper
    {
        return new HereAutosuggestRequestMapper(new Coordinates(-23.5615, -46.6562));
    }

    public function test_coordenada_com_raio_vira_circle_e_nunca_emite_at(): void
    {
        $query = $this->withCenter()->toQuery(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), 3000, ['BR']),
            'pt-BR',
            'BR',
        );

        $this->assertSame('Av Paulista', $query['q']);
        $this->assertSame('pt-BR', $query['lang']);
        $this->assertSame(['circle:-23.5000000,-46.6000000;r=3000', 'countryCode:BRA'], $query['in']);
        // O HERE responde 400 "Mutually exclusive parameters violated" se `at` e
        // `in=circle` vierem juntos. Esta asserção e o portao desse 400.
        $this->assertArrayNotHasKey('at', $query);
    }

    public function test_coordenada_sem_raio_vira_at(): void
    {
        $query = $this->withCenter()->toQuery(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), null, ['BR']),
            'pt-BR',
            'BR',
        );

        $this->assertSame('-23.5000000,-46.6000000', $query['at']);
        $this->assertSame(['countryCode:BRA'], $query['in']);
    }

    public function test_sem_coordenada_cai_no_centro_configurado(): void
    {
        $query = $this->withCenter()->toQuery(new AutocompleteRequest('Av Paulista'), 'pt-BR', 'BR');

        // radiusMeters default e 50000, entao o foco sai como circle.
        $this->assertSame(['circle:' . self::CENTER . ';r=50000', 'countryCode:BRA'], $query['in']);
        $this->assertArrayNotHasKey('at', $query);
    }

    public function test_sem_coordenada_e_sem_raio_usa_o_centro_como_at(): void
    {
        $query = $this->withCenter()->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['BR']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(self::CENTER, $query['at']);
        $this->assertSame(['countryCode:BRA'], $query['in']);
    }

    public function test_sem_coordenada_e_sem_centro_configurado_lanca_excecao_acionavel(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/autosuggest_center|near/');

        // Sem centro: o Autosuggest do HERE nao tem como ser chamado, e um erro
        // do pacote e melhor que um 400 opaco do provider.
        (new HereAutosuggestRequestMapper())->toQuery(new AutocompleteRequest('Av Paulista'), 'pt-BR', 'BR');
    }

    public function test_pais_invalido_falha_pelo_pais_mesmo_sem_centro(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/pais|ISO 3166/i');

        // Precedencia: a conversao de pais acontece antes da resolucao do foco,
        // para o erro apontar o que realmente esta errado na entrada.
        (new HereAutosuggestRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['Brasil']),
            'pt-BR',
            'BR',
        );
    }

    public function test_mapeia_itens_e_anula_place_em_chain_query(): void
    {
        $items = (new HereAutosuggestResponseMapper())->toCollection($this->fixture())->all();

        $this->assertCount(4, $items);

        $this->assertSame(Provider::Here, $items[0]->place->provider);
        $this->assertFalse($items[0]->isEstablishment);

        $this->assertTrue($items[1]->isEstablishment);

        $this->assertNull($items[3]->place, 'chainQuery nao e resolvivel pelo Lookup');
        $this->assertSame('Postos Shell', $items[3]->mainText);
    }

    public function test_separa_main_text_de_secondary_text(): void
    {
        $first = (new HereAutosuggestResponseMapper())->toCollection($this->fixture())->first();

        // O `title` do Autosuggest vem igual ao label inteiro; a linha principal
        // sai dos campos estruturados do address, nao dele.
        $this->assertSame('Avenida Paulista, 1000', $first->mainText);
        $this->assertSame('Bela Vista, Sao Paulo - SP, 01310-100, Brasil', $first->secondaryText);
    }

    public function test_rua_sem_numero_nao_ganha_virgula_no_main_text(): void
    {
        $street = (new HereAutosuggestResponseMapper())->toCollection($this->fixture())->all()[2];

        // Regressao: o `title` deste item e "Rua Blumenau, Joinville - SC,
        // Brasil". Se ele virasse mainText, o consumidor contaria virgulas,
        // concluiria que ja ha numero da casa e aceitaria uma entrega sem numero.
        $this->assertSame('Rua Blumenau', $street->mainText);
        $this->assertStringNotContainsString(',', $street->mainText);
        $this->assertSame('Joinville - SC, Brasil', $street->secondaryText);
    }

    public function test_lugar_mantem_o_nome_proprio_como_linha_principal(): void
    {
        $place = (new HereAutosuggestResponseMapper())->toCollection($this->fixture())->all()[1];

        // Para `place` o title E um nome, nao um endereco: derivar do address
        // trocaria "Shopping Paulista" pela rua onde ele fica.
        $this->assertSame('Shopping Paulista', $place->mainText);
        $this->assertSame('Rua Treze de Maio, Bela Vista, Sao Paulo - SP, Brasil', $place->secondaryText);
    }

    public function test_pede_o_endereco_estruturado_ao_autosuggest(): void
    {
        $query = $this->withCenter()->toQuery(new AutocompleteRequest('Av Paulista'), 'pt-BR', 'BR');

        // Sem `show=details` o address volta so com `label` e nao ha como
        // derivar mainText — ver HereAddressLabel::mainText().
        $this->assertSame('details', $query['show']);
    }

    public function test_converte_pais_fora_das_quatro_entradas_antigas(): void
    {
        $query = $this->withCenter()->toQuery(
            new AutocompleteRequest('Zocalo', null, null, ['MX']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(['countryCode:MEX'], $query['in']);
    }

    public function test_codigo_alpha3_passa_direto(): void
    {
        $query = $this->withCenter()->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['BRA']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(['countryCode:BRA'], $query['in']);
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
        // Precisa do centro: sem coordenada e sem raio, o foco vem do config.
        $query = $this->withCenter()->toQuery(
            new AutocompleteRequest('Fronteira', null, null, ['BR', 'MX']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(['countryCode:BRA,MEX'], $query['in']);
    }
}
