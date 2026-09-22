<?php

namespace BeeDelivery\BeeMaps\Support;

/**
 * O mergeConfigFrom do Laravel faz array_merge de UM nivel: quem publicou
 * config/bee-maps.php mantem o array `google`/`here` inteiro e nunca ve chave
 * nova aninhada — endpoint de servico novo, limite novo, nada. O sintoma no
 * consumidor e "Undefined array key" seguido de TypeError, num upgrade que
 * deveria ser transparente.
 */
final class ConfigMerge
{
    /**
     * @param array<mixed> $padroes   o que o pacote traz
     * @param array<mixed> $publicado o que o consumidor publicou; vence em conflito
     *
     * @return array<mixed>
     */
    public static function deep(array $padroes, array $publicado): array
    {
        // Lista publicada substitui a do pacote por inteiro. Mesclar listas
        // ressuscitaria itens que o consumidor removeu de proposito — o caso
        // concreto e alguem tirar um provider de `providers`.
        if (array_is_list($padroes) && array_is_list($publicado)) {
            return $publicado;
        }

        foreach ($padroes as $chave => $valor) {
            if (! array_key_exists($chave, $publicado)) {
                $publicado[$chave] = $valor;

                continue;
            }

            if (is_array($valor) && is_array($publicado[$chave])) {
                $publicado[$chave] = self::deep($valor, $publicado[$chave]);
            }
        }

        return $publicado;
    }
}
