<?php

namespace BeeDelivery\BeeMaps\Providers\Here\Services;

use BeeDelivery\BeeMaps\Contracts\Services\RouteMatrix;
use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\MatrixTooLargeException;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereMatrixRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereMatrixResponseMapper;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;

final class HereRouteMatrix implements RouteMatrix
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly HereMatrixRequestMapper $requestMapper,
        private readonly HereMatrixResponseMapper $responseMapper,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly ?int $maxElements,
    ) {
    }

    public function matrix(RouteMatrixRequest $request): RouteMatrixEntryCollection
    {
        if ($this->maxElements !== null && $request->elements() > $this->maxElements) {
            throw MatrixTooLargeException::make(Provider::Here, $request->elements(), $this->maxElements);
        }

        $resposta = $this->http->post(
            Provider::Here,
            Service::RouteMatrix,
            // async=false e a D10: o modo assincrono e submit -> poll -> download,
            // que depende de job e nao cabe dentro de uma request HTTP.
            $this->url . '?async=false&apiKey=' . urlencode($this->apiKey),
            $this->requestMapper->toPayload($request),
        );

        return $this->responseMapper->toCollection($resposta);
    }
}
