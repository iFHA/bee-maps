<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Google;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class GoogleGeocodeMapperTest extends TestCase
{
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__ . "/../../../Fixtures/google/{$name}.json"), true);
    }

    public function test_mapeia_componentes_de_endereco(): void
    {
        $result = (new GoogleGeocodeResponseMapper())->toCollection($this->fixture('geocode'))->first();

        $this->assertSame('Avenida Paulista', $result->address->street);
        $this->assertSame('1000', $result->address->number);
        $this->assertSame('Bela Vista', $result->address->neighborhood);
        $this->assertSame('Sao Paulo', $result->address->city);
        $this->assertSame('SP', $result->address->state, 'estado usa short_name');
        $this->assertSame('01310100', $result->address->postalCode, 'CEP sem hifen');
        $this->assertSame(-23.5615, $result->coordinates->latitude);
        $this->assertTrue($result->partial);
        $this->assertNull($result->matchScore, 'o Google nao reporta grau de casamento');
        $this->assertSame(Provider::Google, $result->place->provider);
    }

    public function test_partial_match_false_nao_vira_true(): void
    {
        $result = (new GoogleGeocodeResponseMapper())
            ->toCollection($this->fixture('geocode-partial-match-false'))
            ->first();

        $this->assertFalse($result->partial);
    }

    public function test_zero_results_devolve_colecao_vazia(): void
    {
        $collection = (new GoogleGeocodeResponseMapper())->toCollection($this->fixture('geocode-zero-results'));

        $this->assertTrue($collection->isEmpty());
    }
}
