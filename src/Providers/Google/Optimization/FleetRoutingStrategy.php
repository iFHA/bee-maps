<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Optimization;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\OptimizedWaypoints;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Support\WaypointOrder;

/**
 * Cloud Fleet Routing (optimizeTours). Cada parada vira um `shipment` de uma
 * entrega so, e o veiculo unico parte da origem.
 *
 * Nao e o default: so atende MinDistance quando o config pede explicitamente
 * `min_distance_api = fleet_routing`.
 */
final class FleetRoutingStrategy implements OptimizationStrategy
{
    public function __construct(
        private readonly MapsHttpClient $http,
        private readonly AccessToken $token,
        private readonly string $url,
    ) {
    }

    public function optimize(OptimizeWaypointsRequest $request): OptimizedWaypoints
    {
        $resposta = $this->http->post(
            Provider::Google,
            Service::RouteOptimization,
            $this->url,
            $this->payload($request),
            ['Authorization' => 'Bearer ' . $this->token->valor()],
        );

        $rota = $resposta['routes'][0] ?? null;

        if (! is_array($rota)) {
            throw $this->erro('A Cloud Fleet Routing nao devolveu rota para os pontos informados.');
        }

        $ordem = WaypointOrder::validar(
            array_map(
                static fn (array $visita): int => (int) ($visita['shipmentIndex'] ?? -1),
                array_values($rota['visits'] ?? []),
            ),
            count($request->intermediates),
            Provider::Google,
            'routes[0].visits[].shipmentIndex',
        );

        $metricas = $resposta['metrics']['aggregatedRouteMetrics'] ?? [];

        return new OptimizedWaypoints(
            order: $ordem,
            // proto3 omite valor zero, aqui como no resto da API do Google.
            distance: new Distance((int) ($metricas['travelDistanceMeters'] ?? 0)),
            duration: new Duration($this->segundos($metricas['travelDuration'] ?? null)),
            objective: $request->objective,
            strategy: 'google.fleet_routing',
        );
    }

    private function payload(OptimizeWaypointsRequest $request): array
    {
        $veiculo = ['startLocation' => $this->local($request->origin)];

        // Tour aberto: sem endLocation o veiculo termina na ultima visita. E o
        // equivalente nativo do `end` omitido no findsequence2 do HERE.
        if ($request->destination !== null) {
            $veiculo['endLocation'] = $this->local($request->destination);
        }

        $veiculo += match ($request->objective) {
            OptimizationObjective::MinDistance => ['costPerKilometer' => 1],
            OptimizationObjective::MinTravelTime => ['costPerHour' => 1],
        };

        $shipments = [];

        foreach ($request->intermediates as $parada) {
            // `deliveries` e campo repeated no proto: lista, nao mapa. O pacote
            // legado monta como mapa, e esse caminho nunca rodou em producao.
            $shipments[] = ['deliveries' => [['arrivalLocation' => $this->local($parada)]]];
        }

        return [
            'model' => ['vehicles' => [$veiculo], 'shipments' => $shipments],
            'searchMode' => 'SEARCH_MODE_UNSPECIFIED',
            'considerRoadTraffic' => false,
        ];
    }

    private function local(Coordinates $ponto): array
    {
        return ['latitude' => $ponto->latitude, 'longitude' => $ponto->longitude];
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
