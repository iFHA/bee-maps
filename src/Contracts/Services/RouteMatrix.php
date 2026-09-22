<?php

namespace BeeDelivery\BeeMaps\Contracts\Services;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;

interface RouteMatrix
{
    /**
     * Uma entrada por par origem-destino. Pares sem rota vem com reachable=false,
     * nao ausentes: uma matriz com buracos e pior de consumir que uma com zeros.
     *
     * @throws \BeeDelivery\BeeMaps\Exceptions\MatrixTooLargeException
     *         quando o numero de elementos excede o limite configurado do provider
     */
    public function matrix(RouteMatrixRequest $request): RouteMatrixEntryCollection;
}
