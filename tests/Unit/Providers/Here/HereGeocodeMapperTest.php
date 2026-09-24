<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereGeocodeMapperTest extends TestCase
{
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__ . "/../../../Fixtures/here/{$name}.json"), true);
    }

    public function test_maps_the_address_from_geocode(): void
    {
        $result = (new HereGeocodeResponseMapper())->toCollection($this->fixture('geocode'))->first();

        $this->assertSame('Avenida Paulista', $result->address->street);
        $this->assertSame('1000', $result->address->number);
        $this->assertSame('Bela Vista', $result->address->neighborhood, 'district vira neighborhood');
        $this->assertSame('Sao Paulo', $result->address->city);
        $this->assertSame('SP', $result->address->state, 'usa stateCode');
        $this->assertSame('01310100', $result->address->postalCode, 'CEP sem hifen');
        $this->assertSame(-23.5615, $result->coordinates->latitude);
        $this->assertSame(Provider::Here, $result->place->provider);
    }

    public function test_exposes_the_raw_query_score_as_match_score(): void
    {
        $result = (new HereGeocodeResponseMapper())->toCollection($this->fixture('geocode'))->first();

        $this->assertSame(0.87, $result->matchScore);
    }

    public function test_the_default_threshold_marks_everything_that_is_not_perfect_as_partial(): void
    {
        $result = (new HereGeocodeResponseMapper())->toCollection($this->fixture('geocode'))->first();

        $this->assertTrue($result->partial, 'score 0.87 com limiar 1.0');
    }

    public function test_a_configurable_threshold_changes_the_classification(): void
    {
        $result = (new HereGeocodeResponseMapper(0.8))->toCollection($this->fixture('geocode'))->first();

        $this->assertFalse($result->partial, 'score 0.87 com limiar 0.8 e considerado exato');
        $this->assertSame(0.87, $result->matchScore, 'o score cru nao muda com o limiar');
    }

    public function test_accepts_a_single_object_from_lookup(): void
    {
        $collection = (new HereGeocodeResponseMapper())->toCollection($this->fixture('lookup'));

        $this->assertCount(1, $collection);
        $this->assertSame('Rua Treze de Maio', $collection->first()->address->street);
        $this->assertFalse($collection->first()->partial, 'sem scoring, nao e parcial');
        $this->assertNull($collection->first()->matchScore, 'o /lookup nao devolve scoring');
    }

    public function test_a_response_with_no_items_returns_an_empty_collection(): void
    {
        $collection = (new HereGeocodeResponseMapper())->toCollection(['items' => []]);

        $this->assertTrue($collection->isEmpty());
    }
}
