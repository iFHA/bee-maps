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
        $coordenadas = [];
        $indice = 0;
        $tamanho = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($indice < $tamanho) {
            $lat += $this->proximoDelta($encoded, $indice);
            $lng += $this->proximoDelta($encoded, $indice);

            $coordenadas[] = new Coordinates($lat / 100000, $lng / 100000);
        }

        return $coordenadas;
    }

    private function proximoDelta(string $encoded, int &$indice): int
    {
        $resultado = 0;
        $deslocamento = 0;

        do {
            if (! isset($encoded[$indice])) {
                throw new InvalidRequestException('Polyline do Google truncada: a string acabou no meio de um delta.');
            }

            $codigo = ord($encoded[$indice]);

            // O alfabeto e ASCII 63 ('?') a 126 ('~'): byte fora disso produz
            // $byte negativo, encerra o varint antes da hora e devolve
            // coordenada plausivel e errada em vez de erro — justamente o que o
            // @throws de PolylineDecoder::decode() promete evitar.
            if ($codigo < 63 || $codigo > 126) {
                throw new InvalidRequestException(sprintf(
                    'Caractere invalido em polyline do Google na posicao %d: "%s".',
                    $indice,
                    $encoded[$indice],
                ));
            }

            $byte = $codigo - 63;
            $indice++;

            $resultado |= ($byte & 0x1F) << $deslocamento;
            $deslocamento += 5;
        } while ($byte >= 0x20);

        // Zigzag: bit menos significativo carrega o sinal.
        return ($resultado & 1) !== 0 ? ~($resultado >> 1) : $resultado >> 1;
    }
}
