<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutocompleteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereAutocompleteResponseMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereAutocompleteMapperTest extends TestCase
{
    private function fixture(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/autocomplete.json'), true);
    }

    public function test_sem_coordenada_manda_so_o_filtro_de_pais(): void
    {
        $query = (new HereAutocompleteRequestMapper())
            ->toQuery(new AutocompleteRequest('Rua Blumenau'), 'pt-BR', 'BR');

        // Este e o caso que o /autosuggest nao atende: busca no pais inteiro,
        // sem foco espacial nenhum.
        $this->assertSame(['countryCode:BRA'], $query['in']);
        $this->assertArrayNotHasKey('at', $query);
    }

    public function test_coordenada_com_raio_vira_circle_e_nunca_emite_at(): void
    {
        $query = (new HereAutocompleteRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), 3000, ['BR']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(['circle:-23.5000000,-46.6000000;r=3000', 'countryCode:BRA'], $query['in']);
        $this->assertArrayNotHasKey('at', $query);
    }

    public function test_coordenada_sem_raio_vira_at(): void
    {
        $query = (new HereAutocompleteRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), null, ['BR']),
            'pt-BR',
            'BR',
        );

        $this->assertSame('-23.5000000,-46.6000000', $query['at']);
        $this->assertSame(['countryCode:BRA'], $query['in']);
    }

    public function test_codigo_de_pais_invalido_lanca_excecao(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/XX/');

        (new HereAutocompleteRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['XX']),
            'pt-BR',
            'BR',
        );
    }

    public function test_main_text_sai_do_address_e_nao_do_title_invertido(): void
    {
        $primeira = (new HereAutocompleteResponseMapper())->toCollection($this->fixture())->first();

        // O `title` deste endpoint comeca pelo pais; usa-lo aqui entregaria
        // "Brasil, Sao Paulo - SP, ..." como linha principal.
        $this->assertSame('Avenida Paulista, 1000', $primeira->mainText);
        $this->assertSame('Sao Paulo - SP, 01310-100, Brasil', $primeira->secondaryText);
        $this->assertSame('Avenida Paulista, 1000, Sao Paulo - SP, 01310-100, Brasil', $primeira->description);
    }

    public function test_rua_sem_numero_nao_ganha_virgula_no_main_text(): void
    {
        $rua = (new HereAutocompleteResponseMapper())->toCollection($this->fixture())->all()[1];

        // O consumidor decide se pede o numero da casa contando virgulas no
        // mainText. Uma virgula a mais aqui pularia essa etapa do checkout.
        $this->assertSame('Rua Blumenau', $rua->mainText);
        $this->assertStringNotContainsString(',', $rua->mainText);
        $this->assertSame('Joinville - SC, 89201-000, Brasil', $rua->secondaryText);
    }

    public function test_cidade_usa_a_localidade_como_linha_principal(): void
    {
        $cidade = (new HereAutocompleteResponseMapper())->toCollection($this->fixture())->all()[2];

        $this->assertSame('Belo Horizonte', $cidade->mainText);
        $this->assertSame('MG, Brasil', $cidade->secondaryText);
    }

    public function test_todo_item_e_resolvivel_e_nenhum_e_estabelecimento(): void
    {
        $colecao = (new HereAutocompleteResponseMapper())->toCollection($this->fixture());

        $this->assertCount(3, $colecao);

        foreach ($colecao as $sugestao) {
            // Sem chainQuery/categoryQuery neste endpoint: todo id resolve no /lookup.
            $this->assertNotNull($sugestao->place);
            $this->assertSame(Provider::Here, $sugestao->place->provider);
            // O resultType do /autocomplete nao tem `place`.
            $this->assertFalse($sugestao->isEstablishment);
        }
    }

    public function test_resposta_malformada_vira_colecao_vazia(): void
    {
        $colecao = (new HereAutocompleteResponseMapper())->toCollection(['items' => [['id' => 'sem-titulo']]]);

        $this->assertCount(0, $colecao);
    }
}
