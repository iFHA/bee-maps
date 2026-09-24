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
     * @param array<mixed> $patterns   o que o pacote traz
     * @param array<mixed> $published o que o consumidor publicou; vence em conflito
     *
     * @return array<mixed>
     */
    public static function deep(array $patterns, array $published): array
    {
        // Quando o PADRAO do pacote e uma lista NAO VAZIA, o publicado substitui
        // por inteiro — e reindexado. Basta o padrao ser lista: exigir que o
        // publicado tambem fosse fazia a regra falhar justamente no caso que ela
        // existe para cobrir, o de quem removeu um item com unset/array_filter e
        // ficou com chaves nao sequenciais.
        //
        // Ja a checagem de vazio nao e detalhe: array_is_list([]) e true, entao um
        // padrao `[]` cairia aqui e rodaria array_values() sobre o mapa publicado,
        // descartando as chaves dele.
        if (array_is_list($patterns) && $patterns !== []) {
            return array_values($published);
        }

        foreach ($patterns as $key => $value) {
            if (! array_key_exists($key, $published)) {
                $published[$key] = $value;

                continue;
            }

            if (is_array($value) && is_array($published[$key])) {
                $published[$key] = self::deep($value, $published[$key]);
            }
        }

        return $published;
    }
}
