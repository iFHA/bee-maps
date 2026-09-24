<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Optimization;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\OptimizedWaypoints;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteRequestMapper;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Support\WaypointOrder;

/**
 * computeRoutes com optimizeWaypointOrder. E o caminho do MinTravelTime.
 *
 * O field mask e literal e nao vem do GoogleRouteRequestMapper: o mapper pede
 * geometria e localizacao de perna quando includeLegs esta ligado, e o Google
 * cobra por campo. Aqui so a ordem e as medidas das pernas sao usadas.
 */
final class RoutesStrategy implements OptimizationStrategy
{
    private const FIELD_MASK = 'routes.optimizedIntermediateWaypointIndex,routes.legs.distanceMeters,routes.legs.duration';

    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly GoogleRouteRequestMapper $requestMapper,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly string $language,
    ) {
    }

    public function optimize(OptimizeWaypointsRequest $request): OptimizedWaypoints
    {
        // O computeRoutes EXIGE destination. Tour aberto vira ciclo fechado na
        // origem, e a perna de volta sai dos totais depois — e a unica emulacao
        // que sobrou no pacote, porque o findsequence2 do HERE aceita omitir o fim.
        $open = $request->destination === null;

        $route = new RouteRequest(
            origin: $request->origin,
            destination: $request->destination ?? $request->origin,
            intermediates: $request->intermediates,
            mode: $request->mode,
            optimizeIntermediates: true,
        );

        $response = $this->http->post(
            Provider::Google,
            Service::RouteOptimization,
            $this->url,
            $this->requestMapper->toPayload($route, $this->language),
            [
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => self::FIELD_MASK,
            ],
        );

        $first = $response['routes'][0] ?? null;

        if (! is_array($first)) {
            throw $this->error('O Google nao devolveu rota para os pontos informados.');
        }

        $order = WaypointOrder::validate(
            $first['optimizedIntermediateWaypointIndex'] ?? [],
            count($request->intermediates),
            Provider::Google,
            'optimizedIntermediateWaypointIndex',
        );

        $legs = array_values($first['legs'] ?? []);
        $expected = count($request->intermediates) + 1;

        // Contar pernas aqui verifica o invariante, nao um proxy: uma rota com
        // origem, N intermediarios e destino tem exatamente N+1 pernas. Com
        // menos, somar o que veio produz total silenciosamente menor.
        if (count($legs) !== $expected) {
            throw $this->error(sprintf(
                'O Google devolveu %d pernas para uma rota de %d esperadas.',
                count($legs),
                $expected,
            ));
        }

        if ($open) {
            array_pop($legs);
        }

        $meters = 0;
        $seconds = 0;

        foreach ($legs as $leg) {
            // proto3 omite campo de valor zero: perna de 0 metros (duas paradas
            // na mesma coordenada) chega sem distanceMeters. Ler ausente como
            // zero aqui e decodificar o formato, nao inventar medida.
            $meters += (int) ($leg['distanceMeters'] ?? 0);
            $seconds += $this->seconds($leg['duration'] ?? null);
        }

        return new OptimizedWaypoints(
            order: $order,
            distance: new Distance($meters),
            duration: new Duration($seconds),
            objective: $request->objective,
            strategy: 'google.routes',
        );
    }

    private function seconds(string|int|null $value): int
    {
        return match (true) {
            $value === null => 0,
            is_int($value) => $value,
            default => (int) rtrim($value, 's'),
        };
    }

    private function error(string $message): ProviderRequestException
    {
        return new ProviderRequestException(Provider::Google, Service::RouteOptimization, $message, 200);
    }
}
