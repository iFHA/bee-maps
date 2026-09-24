<?php

namespace BeeDelivery\BeeMaps\Support\Polyline;

use BeeDelivery\BeeMaps\Contracts\PolylineDecoder;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

/**
 * Encoded Polyline Algorithm Format: deltas em zigzag, base64 deslocado em 63,
 * precisao fixa de 5 casas decimais.
 */
final class GoogleEncodedPolylineDecoder implements PolylineDecoder
{
    public function decode(string $encoded): array
    {
        $coordinates = [];
        $index = 0;
        $size = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $size) {
            $lat += $this->nextDelta($encoded, $index);
            $lng += $this->nextDelta($encoded, $index);

            $coordinates[] = new Coordinates($lat / 100000, $lng / 100000);
        }

        return $coordinates;
    }

    private function nextDelta(string $encoded, int &$index): int
    {
        $result = 0;
        $shift = 0;

        do {
            if (! isset($encoded[$index])) {
                throw new InvalidRequestException('Polyline do Google truncada: a string acabou no meio de um delta.');
            }

            $code = ord($encoded[$index]);

            // O alfabeto e ASCII 63 ('?') a 126 ('~'): byte fora disso produz
            // $byte negativo, encerra o varint antes da hora e devolve
            // coordenada plausivel e errada em vez de erro — justamente o que o
            // @throws de PolylineDecoder::decode() promete evitar.
            if ($code < 63 || $code > 126) {
                throw new InvalidRequestException(sprintf(
                    'Caractere invalido em polyline do Google na posicao %d: "%s".',
                    $index,
                    $encoded[$index],
                ));
            }

            $byte = $code - 63;
            $index++;

            $result |= ($byte & 0x1F) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        // Zigzag: bit menos significativo carrega o sinal.
        return ($result & 1) !== 0 ? ~($result >> 1) : $result >> 1;
    }
}
