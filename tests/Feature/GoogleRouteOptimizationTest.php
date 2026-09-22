<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class GoogleRouteOptimizationTest extends TestCase
{
    private function ponto(float $offset): Coordinates
    {
        return new Coordinates(-23.5 - $offset, -46.6 - $offset);
    }

    private function requisicao(OptimizationObjective $objetivo): OptimizeWaypointsRequest
    {
        return new OptimizeWaypointsRequest(
            origin: $this->ponto(0),
            destination: $this->ponto(0.9),
            intermediates: [$this->ponto(0.1), $this->ponto(0.2), $this->ponto(0.3)],
            objective: $objetivo,
        );
    }

    public function test_objetivo_de_tempo_vai_para_o_computeRoutes(): void
    {
        Http::fake(['routes.googleapis.com/directions/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/route-optimized.json'), true),
            200,
        )]);

        $resultado = $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Google)
            ->optimize($this->requisicao(OptimizationObjective::MinTravelTime));

        $this->assertSame('google.routes', $resultado->strategy);
        $this->assertSame([2, 0, 1], $resultado->order);
    }

    public function test_objetivo_de_distancia_vai_para_a_matriz_por_default(): void
    {
        // Sem Http::fake para o computeRoutes: se o seletor mandar o MinDistance
        // para la, preventStrayRequests quebra o teste em vez de passar por acaso.
        Http::fake(['routes.googleapis.com/distanceMatrix/*' => Http::response(
            $this->matrizQuadrada(5),
            200,
        )]);

        $resultado = $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Google)
            ->optimize($this->requisicao(OptimizationObjective::MinDistance));

        $this->assertSame('google.matrix_tsp', $resultado->strategy);
        $this->assertCount(3, $resultado->order);
    }

    /**
     * Matriz NxN onde a distancia entre i e j e |i - j| * 1000 metros: os pontos
     * ficam numa reta na ordem em que foram enviados.
     */
    private function matrizQuadrada(int $n): array
    {
        $elementos = [];

        for ($o = 0; $o < $n; $o++) {
            for ($d = 0; $d < $n; $d++) {
                $metros = abs($o - $d) * 1000;

                $elementos[] = [
                    'originIndex' => $o,
                    'destinationIndex' => $d,
                    'distanceMeters' => $metros,
                    'duration' => ($metros * 6) . 's',
                    'condition' => 'ROUTE_EXISTS',
                ];
            }
        }

        return $elementos;
    }
}
