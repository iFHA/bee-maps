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

    public function test_without_coordinates_it_sends_only_the_country_filter(): void
    {
        $query = (new HereAutocompleteRequestMapper())
            ->toQuery(new AutocompleteRequest('Rua Blumenau'), 'pt-BR', 'BR');

        // Este e o caso que o /autosuggest nao atende: busca no pais inteiro,
        // sem foco espacial nenhum.
        $this->assertSame(['countryCode:BRA'], $query['in']);
        $this->assertArrayNotHasKey('at', $query);
    }

    public function test_coordinates_with_a_radius_become_a_circle_and_never_emit_at(): void
    {
        $query = (new HereAutocompleteRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), 3000, ['BR']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(['circle:-23.5000000,-46.6000000;r=3000', 'countryCode:BRA'], $query['in']);
        $this->assertArrayNotHasKey('at', $query);
    }

    public function test_coordinates_without_a_radius_become_at(): void
    {
        $query = (new HereAutocompleteRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), null, ['BR']),
            'pt-BR',
            'BR',
        );

        $this->assertSame('-23.5000000,-46.6000000', $query['at']);
        $this->assertSame(['countryCode:BRA'], $query['in']);
    }

    public function test_an_invalid_country_code_throws(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/XX/');

        (new HereAutocompleteRequestMapper())->toQuery(
            new AutocompleteRequest('Av Paulista', null, null, ['XX']),
            'pt-BR',
            'BR',
        );
    }

    public function test_main_text_comes_from_the_address_not_from_the_inverted_title(): void
    {
        $first = (new HereAutocompleteResponseMapper())->toCollection($this->fixture())->first();

        // O `title` deste endpoint comeca pelo pais; usa-lo aqui entregaria
        // "Brasil, Sao Paulo - SP, ..." como linha principal.
        $this->assertSame('Avenida Paulista, 1000', $first->mainText);
        $this->assertSame('Sao Paulo - SP, 01310-100, Brasil', $first->secondaryText);
        $this->assertSame('Avenida Paulista, 1000, Sao Paulo - SP, 01310-100, Brasil', $first->description);
    }

    public function test_a_street_without_a_number_gets_no_comma_in_main_text(): void
    {
        $street = (new HereAutocompleteResponseMapper())->toCollection($this->fixture())->all()[1];

        // O consumidor decide se pede o numero da casa contando virgulas no
        // mainText. Uma virgula a mais aqui pularia essa etapa do checkout.
        $this->assertSame('Rua Blumenau', $street->mainText);
        $this->assertStringNotContainsString(',', $street->mainText);
        $this->assertSame('Joinville - SC, 89201-000, Brasil', $street->secondaryText);
    }

    public function test_a_city_uses_the_locality_as_the_main_line(): void
    {
        $city = (new HereAutocompleteResponseMapper())->toCollection($this->fixture())->all()[2];

        $this->assertSame('Belo Horizonte', $city->mainText);
        $this->assertSame('MG, Brasil', $city->secondaryText);
    }

    public function test_every_item_is_resolvable_and_none_is_an_establishment(): void
    {
        $collection = (new HereAutocompleteResponseMapper())->toCollection($this->fixture());

        $this->assertCount(3, $collection);

        foreach ($collection as $suggestion) {
            // Sem chainQuery/categoryQuery neste endpoint: todo id resolve no /lookup.
            $this->assertNotNull($suggestion->place);
            $this->assertSame(Provider::Here, $suggestion->place->provider);
            // O resultType do /autocomplete nao tem `place`.
            $this->assertFalse($suggestion->isEstablishment);
        }
    }

    public function test_a_malformed_response_becomes_an_empty_collection(): void
    {
        $collection = (new HereAutocompleteResponseMapper())->toCollection(['items' => [['id' => 'sem-titulo']]]);

        $this->assertCount(0, $collection);
    }
}
