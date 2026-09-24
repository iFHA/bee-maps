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
     * @param array<int|string, mixed> $order  O que o provider devolveu.
     * @param int                      $total  Quantos intermediarios foram enviados.
     * @param string                   $origin Nome do campo na resposta, para a mensagem.
     *
     * @return list<int>
     *
     * @throws ProviderRequestException
     */
    public static function validate(array $order, int $total, Provider $provider, string $origin): array
    {
        $normalized = array_map('intval', array_values($order));

        if (count($normalized) !== $total) {
            throw self::error($provider, sprintf(
                'O campo %s devolveu %d de %d waypoints intermediarios; '
                . 'seguir com ordem incompleta apagaria paradas da rota.',
                $origin,
                count($normalized),
                $total,
            ));
        }

        $seen = [];

        foreach ($normalized as $index) {
            if ($index < 0 || $index >= $total) {
                throw self::error($provider, sprintf(
                    'O campo %s devolveu o indice %d, fora da faixa de %d intermediarios enviados.',
                    $origin,
                    $index,
                    $total,
                ));
            }

            if (isset($seen[$index])) {
                throw self::error($provider, sprintf(
                    'O campo %s devolveu o indice %d repetido.',
                    $origin,
                    $index,
                ));
            }

            $seen[$index] = true;
        }

        return $normalized;
    }

    private static function error(Provider $provider, string $message): ProviderRequestException
    {
        return new ProviderRequestException($provider, Service::RouteOptimization, $message, 200);
    }
}
