<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\Polyline\GoogleEncodedPolylineDecoder;
use BeeDelivery\BeeMaps\Support\Polyline\HereFlexiblePolylineDecoder;
use BeeDelivery\BeeMaps\Support\ValueObjects\Polyline;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class PolylineTest extends TestCase
{
    public function test_decodifica_polyline_do_google(): void
    {
        $polyline = new Polyline('_p~iF~ps|U_ulLnnqC_mqNvxq`@', new GoogleEncodedPolylineDecoder());

        $coordinates = $polyline->coordinates();

        $this->assertCount(3, $coordinates);
        $this->assertEqualsWithDelta(38.5, $coordinates[0]->latitude, 0.00001);
        $this->assertEqualsWithDelta(-120.2, $coordinates[0]->longitude, 0.00001);
        $this->assertEqualsWithDelta(43.252, $coordinates[2]->latitude, 0.00001);
        $this->assertEqualsWithDelta(-126.453, $coordinates[2]->longitude, 0.00001);
    }

    public function test_decodifica_flexible_polyline_do_here(): void
    {
        $polyline = new Polyline('BFoz5xJ67i1B1B7PzIhaxL7Y', new HereFlexiblePolylineDecoder());

        $coordinates = $polyline->coordinates();

        $this->assertCount(4, $coordinates);
        $this->assertEqualsWithDelta(50.10228, $coordinates[0]->latitude, 0.00001);
        $this->assertEqualsWithDelta(8.69821, $coordinates[0]->longitude, 0.00001);
        $this->assertEqualsWithDelta(50.09878, $coordinates[3]->latitude, 0.00001);
        $this->assertEqualsWithDelta(8.68752, $coordinates[3]->longitude, 0.00001);
    }

    public function test_raw_devolve_a_string_original_sem_decodificar(): void
    {
        $decoder = new class implements \BeeDelivery\BeeMaps\Contracts\PolylineDecoder {
            public int $calls = 0;

            public function decode(string $encoded): array
            {
                $this->calls++;

                return [];
            }
        };

        $polyline = new Polyline('qualquer-coisa', $decoder);

        $this->assertSame('qualquer-coisa', $polyline->raw());
        $this->assertSame(0, $decoder->calls, 'raw() nao pode disparar decodificacao.');
    }

    public function test_decodificacao_e_memoizada(): void
    {
        $decoder = new class implements \BeeDelivery\BeeMaps\Contracts\PolylineDecoder {
            public int $calls = 0;

            public function decode(string $encoded): array
            {
                $this->calls++;

                return [];
            }
        };

        $polyline = new Polyline('qualquer-coisa', $decoder);
        $polyline->coordinates();
        $polyline->coordinates();

        $this->assertSame(1, $decoder->calls);
    }

    public function test_polyline_do_google_truncada_vira_excecao_tipada(): void
    {
        $this->expectException(InvalidRequestException::class);

        // '_p~iF' sozinho e um delta de latitude completo seguido de nada:
        // falta a longitude, entao o decodificador estoura o fim da string.
        (new Polyline('_p~iF', new GoogleEncodedPolylineDecoder()))->coordinates();
    }

    public function test_flexible_polyline_com_caractere_invalido_vira_excecao_tipada(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new Polyline('BFoz5xJ!!!', new HereFlexiblePolylineDecoder()))->coordinates();
    }

    public function test_decodificador_do_google_rejeita_polyline_de_outro_provider(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/caractere|invalid/i');

        // Antes: devolvia 8 pontos perto de (0,0) sem lançar nada. Como raw() e
        // feito para ser persistido e repassado, um raw lido com o decodificador
        // do provider errado dava coordenada plausivel e errada.
        (new Polyline('BFoz5xJ67i1B1B7PzIhaxL7Y', new GoogleEncodedPolylineDecoder()))->coordinates();
    }

    public function test_decodificador_do_google_rejeita_caractere_fora_do_alfabeto(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new Polyline('!!!!', new GoogleEncodedPolylineDecoder()))->coordinates();
    }
}
