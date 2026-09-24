<?php

namespace BeeDelivery\BeeMaps\Providers\Here;

/**
 * Recorte do endereco do HERE nas duas linhas que o contrato expoe (`mainText`
 * e `secondaryText`). Os dois mappers de autocomplete recortam igual, e
 * precisam recortar igual: o teste de paridade compara o formato entre
 * providers, e duas versoes desta regra divergiriam sem quebrar nada visivel
 * ate chegar na tela.
 */
final class HereAddressLabel
{
    /**
     * A linha principal sai dos campos estruturados do `address`, nunca do
     * `title`. Motivo: nos dois endpoints o `title` de um resultado de endereco
     * vem igual ao `address.label` inteiro — usa-lo colapsaria o endereco todo
     * em `mainText` e deixaria `secondaryText` vazio. No /autocomplete o `title`
     * ainda vem em ordem invertida, comecando pelo pais.
     *
     * O numero e casado por prefixo contra o label em vez de concatenado com
     * virgula fixa: o label ja veio montado pela regra postal local, e e ele que
     * decide entre "Avenida Paulista, 1000" e "Pariser Strasse 2".
     *
     * Isso importa alem da estetica — o consumidor conta virgulas em `mainText`
     * para decidir se pede o numero da casa.
     */
    public static function principal(array $endereco, string $label): string
    {
        $rua = $endereco['street'] ?? null;

        if ($rua === null) {
            return $endereco['city']
                ?? $endereco['county']
                ?? $endereco['state']
                ?? $endereco['countryName']
                ?? explode(',', $label)[0];
        }

        $numero = $endereco['houseNumber'] ?? null;

        if ($numero === null) {
            return $rua;
        }

        foreach ([$rua . ', ' . $numero, $rua . ' ' . $numero] as $candidato) {
            if (str_starts_with($label, $candidato)) {
                return $candidato;
            }
        }

        return $rua;
    }

    public static function complemento(string $principal, string $label): string
    {
        if (! str_starts_with($label, $principal)) {
            return $label;
        }

        // O separador entre a linha principal e o resto varia: ", " no caso
        // comum e " - " quando o HERE cola a sigla do estado na cidade
        // ("Belo Horizonte - MG, Brasil"). Charlist so com ASCII de proposito:
        // ltrim opera em bytes, e um travessao no charlist poderia comer o byte
        // inicial de um caractere UTF-8 legitimo.
        return ltrim(substr($label, strlen($principal)), ' ,-');
    }
}
