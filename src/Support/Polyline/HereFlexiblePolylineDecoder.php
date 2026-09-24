<?php

namespace BeeDelivery\BeeMaps\Support\Polyline;

use BeeDelivery\BeeMaps\Contracts\PolylineDecoder;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;

/**
 * Flexible Polyline do HERE: varints em zigzag como no Google, mas com alfabeto
 * proprio e um cabecalho que carrega versao, precisao e terceira dimensao — por
 * isso a precisao NAO pode ser fixada em 5 como no decodificador do Google.
 *
 * A terceira dimensao (elevacao, nivel etc.) e lida para nao desalinhar o fluxo
 * de varints, mas descartada: Coordinates e bidimensional.
 */
final class HereFlexiblePolylineDecoder implements PolylineDecoder
{
    private const FIRST_CHARACTER = 45; // ord('-')

    /** Tabela de decodificacao oficial, indexada por ord($char) - 45. */
    private const DECODING_TABLE = [
        62, -1, -1, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, -1, -1, -1, -1, -1, -1, -1,
        0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21,
        22, 23, 24, 25, -1, -1, -1, -1, 63, -1, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35,
        36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51,
    ];

    public function decode(string $encoded): array
    {
        $index = 0;

        $version = $this->varint($encoded, $index);

        if ($version !== 1) {
            throw new InvalidRequestException("Versao de flexible polyline nao suportada: {$version}.");
        }

        $header = $this->varint($encoded, $index);
        $precision = $header & 15;
        $thirdDimension = ($header >> 4) & 7;

        $factor = 10 ** $precision;
        $coordinates = [];
        $size = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $size) {
            $lat += $this->signedVarint($encoded, $index);
            $lng += $this->signedVarint($encoded, $index);

            if ($thirdDimension !== 0) {
                $this->signedVarint($encoded, $index);
            }

            $coordinates[] = new Coordinates($lat / $factor, $lng / $factor);
        }

        return $coordinates;
    }

    private function varint(string $encoded, int &$index): int
    {
        $result = 0;
        $shift = 0;

        while (true) {
            if (! isset($encoded[$index])) {
                throw new InvalidRequestException('Flexible polyline truncada: a string acabou no meio de um valor.');
            }

            $position = ord($encoded[$index]) - self::FIRST_CHARACTER;
            $value = ($position >= 0 && $position < count(self::DECODING_TABLE))
                ? self::DECODING_TABLE[$position]
                : -1;

            if ($value < 0) {
                throw new InvalidRequestException(
                    "Caractere invalido em flexible polyline na posicao {$index}: '{$encoded[$index]}'.",
                );
            }

            $index++;
            $result |= ($value & 0x1F) << $shift;

            if (($value & 0x20) === 0) {
                return $result;
            }

            $shift += 5;
        }
    }

    private function signedVarint(string $encoded, int &$index): int
    {
        $value = $this->varint($encoded, $index);

        return ($value & 1) !== 0 ? ~($value >> 1) : $value >> 1;
    }
}
