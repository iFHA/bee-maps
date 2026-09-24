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
    public function test_the_minimal_payload_uses_the_language_and_region_from_the_config(): void
    {
        $payload = (new GooglePlaceSearchRequestMapper())
            ->toPayload(new PlaceSearchRequest('farmacia'), 'pt-BR', 'BR');

        $this->assertSame('farmacia', $payload['textQuery']);
        $this->assertSame('pt-BR', $payload['languageCode']);
        $this->assertSame('BR', $payload['regionCode']);
        $this->assertArrayNotHasKey('locationBias', $payload);
    }

    public function test_near_becomes_a_circular_location_bias(): void
    {
        $payload = (new GooglePlaceSearchRequestMapper())
            ->toPayload(new PlaceSearchRequest('farmacia', new Coordinates(-23.5, -46.6)), 'pt-BR', 'BR');

        $this->assertSame(-23.5, $payload['locationBias']['circle']['center']['latitude']);
        $this->assertSame(-46.6, $payload['locationBias']['circle']['center']['longitude']);
    }

    public function test_the_region_from_the_request_takes_precedence_over_the_config(): void
    {
        $payload = (new GooglePlaceSearchRequestMapper())
            ->toPayload(new PlaceSearchRequest('farmacia', null, 'PRT'), 'pt-BR', 'BR');

        // Aceita alpha-3 na entrada e converte: o Google exige alpha-2.
        $this->assertSame('PT', $payload['regionCode']);
    }

    public function test_the_field_mask_asks_for_structured_components(): void
    {
        $mask = (new GooglePlaceSearchRequestMapper())->fieldMask();

        $this->assertStringContainsString('places.id', $mask);
        $this->assertStringContainsString('places.displayName.text', $mask);
        $this->assertStringContainsString('places.location', $mask);
        // D16: componentes estruturados sao SKU Enterprise, e sao pedidos de
        // proposito — sem eles o Address do Google fica so com formatted.
        $this->assertStringContainsString('places.addressComponents', $mask);
    }

    public function test_the_response_becomes_a_typed_collection_with_a_structured_address(): void
    {
        $response = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/place-search.json'), true);

        $collection = (new GooglePlaceSearchResponseMapper())->toCollection($response);

        $this->assertCount(2, $collection);

        $first = $collection->first();
        $this->assertSame('Drogaria Sao Paulo', $first->name);
        $this->assertSame(Provider::Google, $first->place->provider);
        $this->assertSame('ChIJ0WGkg4FEzpQRrlsz_whLqZs', $first->place->id);
        $this->assertSame('Avenida Paulista', $first->address->street);
        $this->assertSame('1000', $first->address->number);
        $this->assertSame('Bela Vista', $first->address->neighborhood);
        $this->assertSame('Sao Paulo', $first->address->city);
        $this->assertSame('SP', $first->address->state);
        $this->assertSame('Brasil', $first->address->country);
        // Mesma regra BR do geocoding: hifen do CEP removido.
        $this->assertSame('01310100', $first->address->postalCode);
        $this->assertEqualsWithDelta(-23.5615, $first->coordinates->latitude, 0.0001);

        $second = $collection->all()[1];
        $this->assertNull($second->address->postalCode);
    }

    public function test_a_response_without_places_becomes_an_empty_collection(): void
    {
        $collection = (new GooglePlaceSearchResponseMapper())->toCollection([]);

        $this->assertTrue($collection->isEmpty());
    }
}
