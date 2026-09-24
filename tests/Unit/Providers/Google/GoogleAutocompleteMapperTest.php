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
    public function test_builds_a_minimal_payload_without_coordinates(): void
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

    public function test_includes_a_circular_restriction_when_coordinates_are_present(): void
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

    public function test_a_null_radius_with_coordinates_sends_no_radius(): void
    {
        $payload = (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), null),
            'pt-BR',
            'BR',
        );

        $this->assertArrayHasKey('locationRestriction', $payload);
        $this->assertArrayNotHasKey('radius', $payload['locationRestriction']['circle']);
    }

    public function test_empty_countries_falls_back_to_the_configured_region(): void
    {
        $payload = (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista'),
            'pt-BR',
            'BR',
        );

        $this->assertSame(['BR'], $payload['includedRegionCodes']);
    }

    public function test_an_alpha3_code_is_converted_to_alpha2(): void
    {
        $payload = (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista', countries: ['BRA']),
            'pt-BR',
            'BR',
        );

        $this->assertSame(['BR'], $payload['includedRegionCodes']);
    }

    public function test_an_invalid_country_code_throws(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista', countries: ['XX']),
            'pt-BR',
            'BR',
        );
    }

    public function test_maps_the_response_to_suggestions(): void
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

    public function test_a_response_without_suggestions_returns_an_empty_collection(): void
    {
        $collection = (new GoogleAutocompleteResponseMapper())->toCollection([]);

        $this->assertTrue($collection->isEmpty());
    }
}
