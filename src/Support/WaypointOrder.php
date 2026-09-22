<?php

namespace BeeDelivery\BeeMaps\Support;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;

/**
 * Ordem devolvida por provider so serve se for permutacao COMPLETA dos
 * intermediarios enviados. Contar elementos nao prova isso: indice repetido e
 * indice fora da faixa somam certo e ainda assim apagam ou inventam parada.
 */
final class WaypointOrder
{
    /**
     * @param array<int|string, mixed> $ordem  O que o provider devolveu.
     * @param int                      $total  Quantos intermediarios foram enviados.
     * @param string                   $origem Nome do campo na resposta, para a mensagem.
     *
     * @return list<int>
     *
     * @throws ProviderRequestException
     */
    public static function validar(array $ordem, int $total, Provider $provider, string $origem): array
    {
        $normalizada = array_map('intval', array_values($ordem));

        if (count($normalizada) !== $total) {
            throw self::erro($provider, sprintf(
                'O campo %s devolveu %d de %d waypoints intermediarios; '
                . 'seguir com ordem incompleta apagaria paradas da rota.',
                $origem,
                count($normalizada),
                $total,
            ));
        }

        $vistos = [];

        foreach ($normalizada as $indice) {
            if ($indice < 0 || $indice >= $total) {
                throw self::erro($provider, sprintf(
                    'O campo %s devolveu o indice %d, fora da faixa de %d intermediarios enviados.',
                    $origem,
                    $indice,
                    $total,
                ));
            }

            if (isset($vistos[$indice])) {
                throw self::erro($provider, sprintf(
                    'O campo %s devolveu o indice %d repetido.',
                    $origem,
                    $indice,
                ));
            }

            $vistos[$indice] = true;
        }

        return $normalizada;
    }

    private static function erro(Provider $provider, string $mensagem): ProviderRequestException
    {
        return new ProviderRequestException($provider, Service::RouteOptimization, $mensagem, 200);
    }
}
