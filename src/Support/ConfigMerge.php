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
        // Quando o PADRAO do pacote e uma lista, o publicado substitui por
        // inteiro — e reindexado. Exigir que o publicado tambem fosse lista
        // fazia a regra falhar justamente no caso que ela existe para cobrir:
        // quem removeu um item com unset/array_filter fica com chaves nao
        // sequenciais, cai no merge por chave e ve o item removido ressuscitar.
        if (array_is_list($padroes)) {
            return array_values($publicado);
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
