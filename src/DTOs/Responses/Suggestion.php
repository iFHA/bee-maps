<?php

namespace BeeDelivery\BeeMaps\DTOs\Responses;

use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;

final readonly class Suggestion
{
    /**
     * @param Coordinates|null $coordinates Posicao da sugestao, quando o provider ja a
     *                                      devolve na propria resposta do autocomplete.
     *                                      Existe para poupar o consumidor de um geocode
     *                                      so para descobrir latitude e longitude do item
     *                                      que o usuario acabou de escolher.
     *
     *                                      E nulo em tres situacoes, e o consumidor precisa
     *                                      tratar todas:
     *                                        - Google: sempre. A predicao do
     *                                          `places:autocomplete` nao carrega posicao.
     *                                        - HERE /autocomplete (busca sem `near`): sempre.
     *                                        - HERE /autosuggest: nos itens `chainQuery` e
     *                                          `categoryQuery`, que nao sao lugares — os
     *                                          mesmos em que `place` tambem e nulo.
     *
     *                                      Quando for nulo, o caminho continua sendo
     *                                      `lookup($place)` ou `geocode($description)`.
     */
    public function __construct(
        public ?PlaceReference $place,
        public string $description,
        public string $mainText,
        public string $secondaryText,
        public bool $isEstablishment,
        public ?Coordinates $coordinates,
    ) {
    }
}
