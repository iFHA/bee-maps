<?php

namespace BeeDelivery\BeeMaps\Contracts\Services;

use BeeDelivery\BeeMaps\DTOs\Requests\PlaceSearchRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\PlaceCollection;

interface PlaceSearch
{
    /**
     * Sem resultado devolve colecao vazia — nunca excecao.
     *
     * @throws \BeeDelivery\BeeMaps\Exceptions\InvalidRequestException
     *         quando o provider exige contexto geografico e nem a requisicao
     *         nem o config fornecem um
     */
    public function search(PlaceSearchRequest $request): PlaceCollection;
}
