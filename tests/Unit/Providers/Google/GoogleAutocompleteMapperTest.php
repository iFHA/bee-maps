<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Google;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteResponseMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class GoogleAutocompleteMapperTest extends TestCase
{
    public function test_monta_payload_minimo_sem_coordenada(): void
    {
        $payload = (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista'),
            'pt-BR',
            'BR',
        );

        $this->assertSame('Av Paulista', $payload['input']);
        $this->assertSame('pt-BR', $payload['languageCode']);
        $this->assertArrayNotHasKey('locationRestriction', $payload);
    }

    public function test_inclui_restricao_circular_quando_ha_coordenada(): void
    {
        $payload = (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), 3000, ['BR']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(-23.5, $payload['locationRestriction']['circle']['center']['latitude']);
        $this->assertSame(3000, $payload['locationRestriction']['circle']['radius']);
        $this->assertSame(['BR'], $payload['includedRegionCodes']);
    }

    public function test_raio_nulo_com_coordenada_nao_envia_radius(): void
    {
        $payload = (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), null),
            'pt-BR',
            'BR',
        );

        $this->assertArrayHasKey('locationRestriction', $payload);
        $this->assertArrayNotHasKey('radius', $payload['locationRestriction']['circle']);
    }

    public function test_countries_vazio_cai_para_a_regiao_configurada(): void
    {
        $payload = (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista'),
            'pt-BR',
            'BR',
        );

        $this->assertSame(['BR'], $payload['includedRegionCodes']);
    }

    public function test_codigo_alpha3_e_convertido_para_alpha2(): void
    {
        $payload = (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista', countries: ['BRA']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(['BR'], $payload['includedRegionCodes']);
    }

    public function test_codigo_de_pais_invalido_lanca_excecao(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista', countries: ['XX']),
            'pt-BR',
            'BR',
        );
    }

    public function test_mapeia_resposta_para_sugestoes(): void
    {
        $json = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/autocomplete.json'), true);

        $collection = (new GoogleAutocompleteResponseMapper())->toCollection($json);

        $this->assertCount(2, $collection);

        $first = $collection->first();
        $this->assertSame('Avenida Paulista, 1000', $first->mainText);
        $this->assertSame(Provider::Google, $first->place->provider);
        $this->assertSame('ChIJ0WGkg4FEzpQRrlsz_whLqZs', $first->place->id);
        $this->assertFalse($first->isEstablishment);

        $this->assertTrue($collection->all()[1]->isEstablishment);
    }

    public function test_resposta_sem_sugestoes_devolve_colecao_vazia(): void
    {
        $collection = (new GoogleAutocompleteResponseMapper())->toCollection([]);

        $this->assertTrue($collection->isEmpty());
    }
}
