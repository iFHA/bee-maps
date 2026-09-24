<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\Polyline\GoogleEncodedPolylineDecoder;
use BeeDelivery\BeeMaps\Support\Polyline\HereFlexiblePolylineDecoder;
use BeeDelivery\BeeMaps\Support\ValueObjects\Polyline;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class PolylineTest extends TestCase
{
    public function test_decodes_a_google_polyline(): void
    {
        $polyline = new Polyline('_p~iF~ps|U_ulLnnqC_mqNvxq`@', new GoogleEncodedPolylineDecoder());

        $coordinates = $polyline->coordinates();

        $this->assertCount(3, $coordinates);
        $this->assertEqualsWithDelta(38.5, $coordinates[0]->latitude, 0.00001);
        $this->assertEqualsWithDelta(-120.2, $coordinates[0]->longitude, 0.00001);
        $this->assertEqualsWithDelta(43.252, $coordinates[2]->latitude, 0.00001);
        $this->assertEqualsWithDelta(-126.453, $coordinates[2]->longitude, 0.00001);
    }

    public function test_decodes_a_here_flexible_polyline(): void
    {
        $polyline = new Polyline('BFoz5xJ67i1B1B7PzIhaxL7Y', new HereFlexiblePolylineDecoder());

        $coordinates = $polyline->coordinates();

        $this->assertCount(4, $coordinates);
        $this->assertEqualsWithDelta(50.10228, $coordinates[0]->latitude, 0.00001);
        $this->assertEqualsWithDelta(8.69821, $coordinates[0]->longitude, 0.00001);
        $this->assertEqualsWithDelta(50.09878, $coordinates[3]->latitude, 0.00001);
        $this->assertEqualsWithDelta(8.68752, $coordinates[3]->longitude, 0.00001);
    }

    public function test_raw_returns_the_original_string_undecoded(): void
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

    public function test_decoding_is_memoized(): void
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

    public function test_a_truncated_google_polyline_becomes_a_typed_exception(): void
    {
        $this->expectException(InvalidRequestException::class);

        // '_p~iF' sozinho e um delta de latitude completo seguido de nada:
        // falta a longitude, entao o decodificador estoura o fim da string.
        (new Polyline('_p~iF', new GoogleEncodedPolylineDecoder()))->coordinates();
    }

    public function test_a_flexible_polyline_with_an_invalid_character_becomes_a_typed_exception(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new Polyline('BFoz5xJ!!!', new HereFlexiblePolylineDecoder()))->coordinates();
    }

    public function test_the_google_decoder_rejects_a_polyline_from_another_provider(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/caractere|invalid/i');

        // Antes: devolvia 8 pontos perto de (0,0) sem lançar nada. Como raw() e
        // feito para ser persistido e repassado, um raw lido com o decodificador
        // do provider errado dava coordenada plausivel e errada.
        (new Polyline('BFoz5xJ67i1B1B7PzIhaxL7Y', new GoogleEncodedPolylineDecoder()))->coordinates();
    }

    public function test_the_google_decoder_rejects_a_character_outside_the_alphabet(): void
    {
        $this->expectException(InvalidRequestException::class);

        (new Polyline('!!!!', new GoogleEncodedPolylineDecoder()))->coordinates();
    }
}
