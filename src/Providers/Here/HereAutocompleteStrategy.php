<?php

namespace BeeDelivery\BeeMaps\Providers\Here;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Exceptions\ConfigurationException;

/**
 * Qual endpoint do HERE atende `autocomplete()`. Sao dois, e nenhum faz o que
 * o outro faz:
 *
 *   /autosuggest   devolve POI alem de endereco, mas EXIGE foco espacial
 *                  (`at`, `in=circle`, `in=bbox` ou `in=ring`). O spec do GS7
 *                  diz que `in=countryCode` "must be accompanied by exactly one
 *                  of at, in=circle or in=bbox" — ou seja, este endpoint nao
 *                  consegue buscar no pais inteiro. O /discover compartilha a
 *                  mesma definicao de parametro e a mesma restricao.
 *   /autocomplete  tem `at` e `in` opcionais e aceita `in=countryCode` sozinho,
 *                  entao cobre o pais inteiro, mas so devolve endereco e area
 *                  administrativa: nao ha `resultType` de lugar.
 */
enum HereAutocompleteStrategy: string
{
    /**
     * Escolhe pelo que a requisicao traz. Com `near` existe foco, entao vale o
     * /autosuggest e seus POI. Sem `near` a intencao e busca ampla — cadastro de
     * empresa em outro estado, por exemplo — e so o /autocomplete atende.
     */
    case Auto = 'auto';

    case Autosuggest = 'autosuggest';

    case Autocomplete = 'autocomplete';

    public static function fromConfig(mixed $valor): self
    {
        if ($valor === null || $valor === '') {
            return self::Auto;
        }

        return self::tryFrom((string) $valor) ?? throw new ConfigurationException(sprintf(
            'bee-maps.here.autocomplete_strategy invalido: "%s". Valores aceitos: %s.',
            (string) $valor,
            implode(', ', array_column(self::cases(), 'value')),
        ));
    }

    /**
     * Resolve `Auto` no endpoint concreto; as outras duas se resolvem em si
     * mesmas. O `autosuggest_center` NAO entra nesta decisao de proposito: se
     * entrasse, um centro esquecido no config reativaria em silencio o recorte
     * local numa busca que pediu alcance nacional.
     */
    public function resolver(AutocompleteRequest $request): self
    {
        if ($this !== self::Auto) {
            return $this;
        }

        return $request->near !== null ? self::Autosuggest : self::Autocomplete;
    }
}
