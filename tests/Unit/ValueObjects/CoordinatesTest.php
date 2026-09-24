<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\ValueObjects;

use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CoordinatesTest extends TestCase
{
    public function test_formata_como_lat_lng(): void
    {
        $this->assertSame('-23.5505000,-46.6333000', (new Coordinates(-23.5505, -46.6333))->toString());
    }

    public function test_formata_zero_sem_virar_apenas_0(): void
    {
        $this->assertSame('0.0000000,0.0000000', (new Coordinates(0.0, 0.0))->toString());
    }

    public function test_formata_valor_pequeno_sem_notacao_cientifica(): void
    {
        $this->assertSame('0.0000005,-0.0000005', (new Coordinates(0.0000005, -0.0000005))->toString());
    }

    public function test_rejeita_latitude_fora_do_intervalo(): void
    {
        $this->expectException(InvalidRequestException::class);

        new Coordinates(91.0, 0.0);
    }

    public function test_rejeita_longitude_fora_do_intervalo(): void
    {
        $this->expectException(InvalidRequestException::class);

        new Coordinates(0.0, 181.0);
    }

    public function test_from_string_aceita_o_formato_que_to_string_produz(): void
    {
        $original = new Coordinates(-23.5615, -46.6562);

        $rebuilt = Coordinates::fromString($original->toString());

        $this->assertEqualsWithDelta($original->latitude, $rebuilt->latitude, 0.0000001);
        $this->assertEqualsWithDelta($original->longitude, $rebuilt->longitude, 0.0000001);
    }

    public function test_from_string_tolera_espacos_em_volta(): void
    {
        $coordinate = Coordinates::fromString('  -23.5 , -46.6  ');

        $this->assertSame(-23.5, $coordinate->latitude);
        $this->assertSame(-46.6, $coordinate->longitude);
    }

    public static function invalidPairs(): array
    {
        return [
            'vazio' => [''],
            'so latitude' => ['-23.5'],
            'tres partes' => ['-23.5,-46.6,10'],
            'nao numerico' => ['sao paulo,-46.6'],
            'separador errado' => ['-23.5;-46.6'],
        ];
    }

    #[DataProvider('invalidPairs')]
    public function test_from_string_rejeita_par_malformado(string $pair): void
    {
        $this->expectException(InvalidRequestException::class);

        Coordinates::fromString($pair);
    }

    public function test_from_string_ainda_valida_a_faixa(): void
    {
        $this->expectException(InvalidRequestException::class);

        Coordinates::fromString('-91,0');
    }
}
