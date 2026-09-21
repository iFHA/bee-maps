<?php

namespace BeeDelivery\BeeMaps\Contracts\Services;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\Route;

interface Routing
{
    /**
     * @throws \BeeDelivery\BeeMaps\Exceptions\InvalidRequestException
     *         quando o provider nao devolve rota para os pontos informados
     */
    public function route(RouteRequest $request): Route;
}
