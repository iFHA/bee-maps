<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Services;

use BeeDelivery\BeeMaps\Contracts\Services\RouteMatrix;
use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\MatrixTooLargeException;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteMatrixRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteMatrixResponseMapper;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class GoogleRouteMatrix implements RouteMatrix
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly GoogleRouteMatrixRequestMapper $requestMapper,
        private readonly GoogleRouteMatrixResponseMapper $responseMapper,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly ?int $maxElements,
    ) {
    }

    public function matrix(RouteMatrixRequest $request): RouteMatrixEntryCollection
    {
        // Falhar aqui, e nao no provider, troca um 400 generico por uma excecao
        // que diz o tamanho pedido e o limite — que e o que o chamador precisa
        // para decidir o tamanho do lote.
        if ($this->maxElements !== null && $request->elements() > $this->maxElements) {
            throw MatrixTooLargeException::make(Provider::Google, $request->elements(), $this->maxElements);
        }

        $resposta = $this->http->post(
            Provider::Google,
            Service::RouteMatrix,
            $this->url,
            $this->requestMapper->toPayload($request),
            [
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => $this->requestMapper->fieldMask(),
            ],
        );

        return $this->responseMapper->toCollection(
            $resposta,
            count($request->origins),
            count($request->destinations),
        );
    }
}
