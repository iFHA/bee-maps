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
        $aberto = $request->destination === null;

        $rota = new RouteRequest(
            origin: $request->origin,
            destination: $request->destination ?? $request->origin,
            intermediates: $request->intermediates,
            mode: $request->mode,
            optimizeIntermediates: true,
        );

        $resposta = $this->http->post(
            Provider::Google,
            Service::RouteOptimization,
            $this->url,
            $this->requestMapper->toPayload($rota, $this->language),
            [
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => self::FIELD_MASK,
            ],
        );

        $primeira = $resposta['routes'][0] ?? null;

        if (! is_array($primeira)) {
            throw $this->erro('O Google nao devolveu rota para os pontos informados.');
        }

        $ordem = WaypointOrder::validar(
            $primeira['optimizedIntermediateWaypointIndex'] ?? [],
            count($request->intermediates),
            Provider::Google,
            'optimizedIntermediateWaypointIndex',
        );

        $pernas = array_values($primeira['legs'] ?? []);
        $esperadas = count($request->intermediates) + 1;

        // Contar pernas aqui verifica o invariante, nao um proxy: uma rota com
        // origem, N intermediarios e destino tem exatamente N+1 pernas. Com
        // menos, somar o que veio produz total silenciosamente menor.
        if (count($pernas) !== $esperadas) {
            throw $this->erro(sprintf(
                'O Google devolveu %d pernas para uma rota de %d esperadas.',
                count($pernas),
                $esperadas,
            ));
        }

        if ($aberto) {
            array_pop($pernas);
        }

        $metros = 0;
        $segundos = 0;

        foreach ($pernas as $perna) {
            // proto3 omite campo de valor zero: perna de 0 metros (duas paradas
            // na mesma coordenada) chega sem distanceMeters. Ler ausente como
            // zero aqui e decodificar o formato, nao inventar medida.
            $metros += (int) ($perna['distanceMeters'] ?? 0);
            $segundos += $this->segundos($perna['duration'] ?? null);
        }

        return new OptimizedWaypoints(
            order: $ordem,
            distance: new Distance($metros),
            duration: new Duration($segundos),
            objective: $request->objective,
            strategy: 'google.routes',
        );
    }

    private function segundos(string|int|null $valor): int
    {
        return match (true) {
            $valor === null => 0,
            is_int($valor) => $valor,
            default => (int) rtrim($valor, 's'),
        };
    }

    private function erro(string $mensagem): ProviderRequestException
    {
        return new ProviderRequestException(Provider::Google, Service::RouteOptimization, $mensagem, 200);
    }
}
