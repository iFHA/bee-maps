<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Google;

use BeeDelivery\BeeMaps\DTOs\Requests\PlaceSearchRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GooglePlaceSearchRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GooglePlaceSearchResponseMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class GooglePlaceSearchMapperTest extends TestCase
{
    public function test_payload_minimo_usa_idioma_e_regiao_do_config(): void
    {
        $payload = (new GooglePlaceSearchRequestMapper())
            ->toPayload(new PlaceSearchRequest('farmacia'), 'pt-BR', 'BR');

        $this->assertSame('farmacia', $payload['textQuery']);
        $this->assertSame('pt-BR', $payload['languageCode']);
        $this->assertSame('BR', $payload['regionCode']);
        $this->assertArrayNotHasKey('locationBias', $payload);
    }

    public function test_near_vira_location_bias_circular(): void
    {
        $payload = (new GooglePlaceSearchRequestMapper())
            ->toPayload(new PlaceSearchRequest('farmacia', new Coordinates(-23.5, -46.6)), 'pt-BR', 'BR');

        $this->assertSame(-23.5, $payload['locationBias']['circle']['center']['latitude']);
        $this->assertSame(-46.6, $payload['locationBias']['circle']['center']['longitude']);
    }

    public function test_region_da_requisicao_tem_precedencia_sobre_o_config(): void
    {
        $payload = (new GooglePlaceSearchRequestMapper())
            ->toPayload(new PlaceSearchRequest('farmacia', null, 'PRT'), 'pt-BR', 'BR');

        // Aceita alpha-3 na entrada e converte: o Google exige alpha-2.
        $this->assertSame('PT', $payload['regionCode']);
    }

    public function test_field_mask_pede_componentes_estruturados(): void
    {
        $mask = (new GooglePlaceSearchRequestMapper())->fieldMask();

        $this->assertStringContainsString('places.id', $mask);
        $this->assertStringContainsString('places.displayName.text', $mask);
        $this->assertStringContainsString('places.location', $mask);
        // D16: componentes estruturados sao SKU Enterprise, e sao pedidos de
        // proposito — sem eles o Address do Google fica so com formatted.
        $this->assertStringContainsString('places.addressComponents', $mask);
    }

    public function test_resposta_vira_colecao_tipada_com_endereco_estruturado(): void
    {
        $resposta = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/place-search.json'), true);

        $colecao = (new GooglePlaceSearchResponseMapper())->toCollection($resposta);

        $this->assertCount(2, $colecao);

        $primeiro = $colecao->first();
        $this->assertSame('Drogaria Sao Paulo', $primeiro->name);
        $this->assertSame(Provider::Google, $primeiro->place->provider);
        $this->assertSame('ChIJ0WGkg4FEzpQRrlsz_whLqZs', $primeiro->place->id);
        $this->assertSame('Avenida Paulista', $primeiro->address->street);
        $this->assertSame('1000', $primeiro->address->number);
        $this->assertSame('Bela Vista', $primeiro->address->neighborhood);
        $this->assertSame('Sao Paulo', $primeiro->address->city);
        $this->assertSame('SP', $primeiro->address->state);
        $this->assertSame('Brasil', $primeiro->address->country);
        // Mesma regra BR do geocoding: hifen do CEP removido.
        $this->assertSame('01310100', $primeiro->address->postalCode);
        $this->assertEqualsWithDelta(-23.5615, $primeiro->coordinates->latitude, 0.0001);

        $segundo = $colecao->all()[1];
        $this->assertNull($segundo->address->postalCode);
    }

    public function test_resposta_sem_places_vira_colecao_vazia(): void
    {
        $colecao = (new GooglePlaceSearchResponseMapper())->toCollection([]);

        $this->assertTrue($colecao->isEmpty());
    }
}
