<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\CountryCode;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class CountryCodeTest extends TestCase
{
    public function test_toAlpha3_converte_alpha2(): void
    {
        $this->assertSame('BRA', CountryCode::toAlpha3('BR'));
    }

    public function test_toAlpha3_aceita_minusculo(): void
    {
        $this->assertSame('BRA', CountryCode::toAlpha3('br'));
    }

    public function test_toAlpha3_passa_alpha3_direto(): void
    {
        $this->assertSame('BRA', CountryCode::toAlpha3('BRA'));
    }

    public function test_toAlpha3_codigo_invalido_lanca_excecao(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/XX/');

        CountryCode::toAlpha3('XX');
    }

    public function test_toAlpha2_converte_alpha3(): void
    {
        $this->assertSame('BR', CountryCode::toAlpha2('BRA'));
    }

    public function test_toAlpha2_passa_alpha2_direto(): void
    {
        $this->assertSame('BR', CountryCode::toAlpha2('BR'));
    }

    public function test_toAlpha2_aceita_minusculo(): void
    {
        $this->assertSame('BR', CountryCode::toAlpha2('bra'));
    }

    public function test_toAlpha2_codigo_alpha2_invalido_lanca_excecao(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/XX/');

        CountryCode::toAlpha2('XX');
    }

    public function test_toAlpha2_codigo_alpha3_invalido_lanca_excecao(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/XXX/');

        CountryCode::toAlpha2('XXX');
    }

    public function test_round_trip_todos_os_codigos_da_tabela(): void
    {
        foreach (['BR', 'US', 'MX', 'DE', 'JP', 'ZW'] as $alpha2) {
            $alpha3 = CountryCode::toAlpha3($alpha2);
            $this->assertSame($alpha2, CountryCode::toAlpha2($alpha3));
        }
    }
}
