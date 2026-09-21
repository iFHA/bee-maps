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
    private const PRIMEIRO_CARACTERE = 45; // ord('-')

    /** Tabela de decodificacao oficial, indexada por ord($char) - 45. */
    private const DECODING_TABLE = [
        62, -1, -1, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, -1, -1, -1, -1, -1, -1, -1,
        0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21,
        22, 23, 24, 25, -1, -1, -1, -1, 63, -1, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35,
        36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51,
    ];

    public function decode(string $encoded): array
    {
        $indice = 0;

        $versao = $this->varint($encoded, $indice);

        if ($versao !== 1) {
            throw new InvalidRequestException("Versao de flexible polyline nao suportada: {$versao}.");
        }

        $cabecalho = $this->varint($encoded, $indice);
        $precisao = $cabecalho & 15;
        $terceiraDimensao = ($cabecalho >> 4) & 7;

        $fator = 10 ** $precisao;
        $coordenadas = [];
        $tamanho = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($indice < $tamanho) {
            $lat += $this->varintComSinal($encoded, $indice);
            $lng += $this->varintComSinal($encoded, $indice);

            if ($terceiraDimensao !== 0) {
                $this->varintComSinal($encoded, $indice);
            }

            $coordenadas[] = new Coordinates($lat / $fator, $lng / $fator);
        }

        return $coordenadas;
    }

    private function varint(string $encoded, int &$indice): int
    {
        $resultado = 0;
        $deslocamento = 0;

        while (true) {
            if (! isset($encoded[$indice])) {
                throw new InvalidRequestException('Flexible polyline truncada: a string acabou no meio de um valor.');
            }

            $posicao = ord($encoded[$indice]) - self::PRIMEIRO_CARACTERE;
            $valor = ($posicao >= 0 && $posicao < count(self::DECODING_TABLE))
                ? self::DECODING_TABLE[$posicao]
                : -1;

            if ($valor < 0) {
                throw new InvalidRequestException(
                    "Caractere invalido em flexible polyline na posicao {$indice}: '{$encoded[$indice]}'.",
                );
            }

            $indice++;
            $resultado |= ($valor & 0x1F) << $deslocamento;

            if (($valor & 0x20) === 0) {
                return $resultado;
            }

            $deslocamento += 5;
        }
    }

    private function varintComSinal(string $encoded, int &$indice): int
    {
        $valor = $this->varint($encoded, $indice);

        return ($valor & 1) !== 0 ? ~($valor >> 1) : $valor >> 1;
    }
}
