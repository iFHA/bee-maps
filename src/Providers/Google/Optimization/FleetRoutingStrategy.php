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
        $response = $this->http->post(
            Provider::Google,
            Service::RouteOptimization,
            $this->url,
            $this->payload($request),
            ['Authorization' => 'Bearer ' . $this->token->value()],
        );

        $route = $response['routes'][0] ?? null;

        if (! is_array($route)) {
            throw $this->error('A Cloud Fleet Routing nao devolveu rota para os pontos informados.');
        }

        $order = WaypointOrder::validate(
            array_map(
                static fn (array $visit): int => (int) ($visit['shipmentIndex'] ?? -1),
                array_values($route['visits'] ?? []),
            ),
            count($request->intermediates),
            Provider::Google,
            'routes[0].visits[].shipmentIndex',
        );

        $metrics = $response['metrics']['aggregatedRouteMetrics'] ?? [];

        return new OptimizedWaypoints(
            order: $order,
            // proto3 omite valor zero, aqui como no resto da API do Google.
            distance: new Distance((int) ($metrics['travelDistanceMeters'] ?? 0)),
            duration: new Duration($this->seconds($metrics['travelDuration'] ?? null)),
            objective: $request->objective,
            strategy: 'google.fleet_routing',
        );
    }

    private function payload(OptimizeWaypointsRequest $request): array
    {
        $vehicle = ['startLocation' => $this->location($request->origin)];

        // Tour aberto: sem endLocation o veiculo termina na ultima visita. E o
        // equivalente nativo do `end` omitido no findsequence2 do HERE.
        if ($request->destination !== null) {
            $vehicle['endLocation'] = $this->location($request->destination);
        }

        $vehicle += match ($request->objective) {
            OptimizationObjective::MinDistance => ['costPerKilometer' => 1],
            OptimizationObjective::MinTravelTime => ['costPerHour' => 1],
        };

        $shipments = [];

        foreach ($request->intermediates as $stop) {
            // `deliveries` e campo repeated no proto: lista, nao mapa. O pacote
            // legado monta como mapa, e esse caminho nunca rodou em producao.
            $shipments[] = ['deliveries' => [['arrivalLocation' => $this->location($stop)]]];
        }

        return [
            'model' => ['vehicles' => [$vehicle], 'shipments' => $shipments],
            'searchMode' => 'SEARCH_MODE_UNSPECIFIED',
            'considerRoadTraffic' => false,
        ];
    }

    private function location(Coordinates $point): array
    {
        return ['latitude' => $point->latitude, 'longitude' => $point->longitude];
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
