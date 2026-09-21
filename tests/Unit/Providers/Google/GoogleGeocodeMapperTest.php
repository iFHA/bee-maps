<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Google;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleGeocodeResponseMapper;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class GoogleGeocodeMapperTest extends TestCase
{
    private function fixture(string $nome): array
    {
        return json_decode(file_get_contents(__DIR__ . "/../../../Fixtures/google/{$nome}.json"), true);
    }

    public function test_mapeia_componentes_de_endereco(): void
    {
        $resultado = (new GoogleGeocodeResponseMapper())->toCollection($this->fixture('geocode'))->first();

        $this->assertSame('Avenida Paulista', $resultado->address->street);
        $this->assertSame('1000', $resultado->address->number);
        $this->assertSame('Bela Vista', $resultado->address->neighborhood);
        $this->assertSame('Sao Paulo', $resultado->address->city);
        $this->assertSame('SP', $resultado->address->state, 'estado usa short_name');
        $this->assertSame('01310100', $resultado->address->postalCode, 'CEP sem hifen');
        $this->assertSame(-23.5615, $resultado->coordinates->latitude);
        $this->assertTrue($resultado->partial);
        $this->assertNull($resultado->matchScore, 'o Google nao reporta grau de casamento');
        $this->assertSame(Provider::Google, $resultado->place->provider);
    }

    public function test_partial_match_false_nao_vira_true(): void
    {
        $resultado = (new GoogleGeocodeResponseMapper())
            ->toCollection($this->fixture('geocode-partial-match-false'))
            ->first();

        $this->assertFalse($resultado->partial);
    }

    public function test_zero_results_devolve_colecao_vazia(): void
    {
        $colecao = (new GoogleGeocodeResponseMapper())->toCollection($this->fixture('geocode-zero-results'));

        $this->assertTrue($colecao->isEmpty());
    }
}
