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

    public function test_coordinates_with_a_radius_become_a_circle_and_never_emit_at(): void
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

    public function test_coordinates_without_a_radius_become_at(): void
    {
        $query = $this->withCenter()->toQuery(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), null, ['BR']),
            'pt-BR',
            'BR',
        );

        $this->assertSame('-23.5000000,-46.6000000', $query['at']);
        $this->assertSame(['countryCode:BRA'], $query['in']);
    }

    public function test_without_coordinates_it_falls_back_to_the_configured_center(): void
    {
        $query = $this->withCenter()->toQuery(new AutocompleteRequest('Av Paulista'), 'pt-BR', 'BR');

        // radiusMeters default e 50000, entao o foco sai como circle.
        $this->assertSame(['circle:' . self::CENTER . ';r=50000', 'countryCode:BRA'], $query['in']);
        $this->assertArrayNotHasKey('at', $query);
    }

    public function test_without_coordinates_or_radius_it_uses_the_center_as_at(): void
    {
        $query = $this->withCenter()->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['BR']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(self::CENTER, $query['at']);
        $this->assertSame(['countryCode:BRA'], $query['in']);
    }

    public function test_without_coordinates_or_a_configured_center_it_throws_an_actionable_exception(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/autosuggest_center|near/');

        // Sem centro: o Autosuggest do HERE nao tem como ser chamado, e um erro
        // do pacote e melhor que um 400 opaco do provider.
        (new HereAutosuggestRequestMapper())->toQuery(new AutocompleteRequest('Av Paulista'), 'pt-BR', 'BR');
    }

    public function test_an_invalid_country_fails_on_the_country_even_without_a_center(): void
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

    public function test_maps_items_and_nulls_place_on_a_chain_query(): void
    {
        $items = (new HereAutosuggestResponseMapper())->toCollection($this->fixture())->all();

        $this->assertCount(4, $items);

        $this->assertSame(Provider::Here, $items[0]->place->provider);
        $this->assertFalse($items[0]->isEstablishment);

        $this->assertTrue($items[1]->isEstablishment);

        $this->assertNull($items[3]->place, 'chainQuery nao e resolvivel pelo Lookup');
        $this->assertSame('Postos Shell', $items[3]->mainText);
    }

    public function test_separates_main_text_from_secondary_text(): void
    {
        $first = (new HereAutosuggestResponseMapper())->toCollection($this->fixture())->first();

        // O `title` do Autosuggest vem igual ao label inteiro; a linha principal
        // sai dos campos estruturados do address, nao dele.
        $this->assertSame('Avenida Paulista, 1000', $first->mainText);
        $this->assertSame('Bela Vista, Sao Paulo - SP, 01310-100, Brasil', $first->secondaryText);
    }

    public function test_a_street_without_a_number_gets_no_comma_in_main_text(): void
    {
        $street = (new HereAutosuggestResponseMapper())->toCollection($this->fixture())->all()[2];

        // Regressao: o `title` deste item e "Rua Blumenau, Joinville - SC,
        // Brasil". Se ele virasse mainText, o consumidor contaria virgulas,
        // concluiria que ja ha numero da casa e aceitaria uma entrega sem numero.
        $this->assertSame('Rua Blumenau', $street->mainText);
        $this->assertStringNotContainsString(',', $street->mainText);
        $this->assertSame('Joinville - SC, Brasil', $street->secondaryText);
    }

    public function test_a_place_keeps_its_proper_name_as_the_main_line(): void
    {
        $place = (new HereAutosuggestResponseMapper())->toCollection($this->fixture())->all()[1];

        // Para `place` o title E um nome, nao um endereco: derivar do address
        // trocaria "Shopping Paulista" pela rua onde ele fica.
        $this->assertSame('Shopping Paulista', $place->mainText);
        $this->assertSame('Rua Treze de Maio, Bela Vista, Sao Paulo - SP, Brasil', $place->secondaryText);
    }

    public function test_asks_autosuggest_for_the_structured_address(): void
    {
        $query = $this->withCenter()->toQuery(new AutocompleteRequest('Av Paulista'), 'pt-BR', 'BR');

        // Sem `show=details` o address volta so com `label` e nao ha como
        // derivar mainText — ver HereAddressLabel::mainText().
        $this->assertSame('details', $query['show']);
    }

    public function test_converts_a_country_outside_the_four_legacy_entries(): void
    {
        $query = $this->withCenter()->toQuery(
            new AutocompleteRequest('Zocalo', null, null, ['MX']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(['countryCode:MEX'], $query['in']);
    }

    public function test_an_alpha3_code_passes_through(): void
    {
        $query = $this->withCenter()->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['BRA']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(['countryCode:BRA'], $query['in']);
    }

    public function test_an_invalid_country_code_throws(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/XX/');

        (new HereAutosuggestRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['XX']),
            'pt-BR',
            'BR',
        );
    }

    public function test_a_spelled_out_country_name_throws(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/Brasil/');

        (new HereAutosuggestRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['Brasil']),
            'pt-BR',
            'BR',
        );
    }

    public function test_multiple_countries_are_converted_and_joined(): void
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
