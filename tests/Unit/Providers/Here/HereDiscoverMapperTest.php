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
    public function test_without_coordinates_it_uses_the_config_region_as_the_in_filter(): void
    {
        $query = (new HereDiscoverRequestMapper())
            ->toQuery(new PlaceSearchRequest('farmacia'), 'pt-BR', 'BR');

        $this->assertSame('farmacia', $query['q']);
        $this->assertSame('pt-BR', $query['lang']);
        $this->assertSame('countryCode:BRA', $query['in']);
        $this->assertArrayNotHasKey('at', $query);
    }

    public function test_coordinates_become_at_and_drop_the_in_filter(): void
    {
        $query = (new HereDiscoverRequestMapper())
            ->toQuery(new PlaceSearchRequest('farmacia', new Coordinates(-23.5, -46.6)), 'pt-BR', 'BR');

        $this->assertSame('-23.5000000,-46.6000000', $query['at']);
        $this->assertArrayNotHasKey('in', $query);
    }

    public function test_without_coordinates_or_region_it_throws_stating_what_was_missing(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/at|regi/i');

        (new HereDiscoverRequestMapper())->toQuery(new PlaceSearchRequest('farmacia'), 'pt-BR', '');
    }

    public function test_the_response_becomes_a_typed_collection(): void
    {
        $response = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/discover.json'), true);

        $collection = (new HereDiscoverResponseMapper())->toCollection($response);

        $this->assertCount(2, $collection);

        $first = $collection->first();
        $this->assertSame('Drogaria Sao Paulo', $first->name);
        $this->assertSame(Provider::Here, $first->place->provider);
        $this->assertSame('here:pds:place:076c3nbf-1234567890abcdef', $first->place->id);
        $this->assertSame('Avenida Paulista', $first->address->street);
        $this->assertSame('1000', $first->address->number);
        $this->assertSame('Bela Vista', $first->address->neighborhood);
        $this->assertSame('Sao Paulo', $first->address->city);
        $this->assertSame('SP', $first->address->state);
        $this->assertSame('Brasil', $first->address->country);
        $this->assertSame('01310100', $first->address->postalCode);
        $this->assertEqualsWithDelta(-23.5615, $first->coordinates->latitude, 0.0001);

        $this->assertNull($collection->all()[1]->address->postalCode);
    }

    public function test_a_response_without_items_becomes_an_empty_collection(): void
    {
        $this->assertTrue((new HereDiscoverResponseMapper())->toCollection([])->isEmpty());
    }
}
