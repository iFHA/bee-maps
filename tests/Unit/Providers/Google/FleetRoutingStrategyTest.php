<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Google;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Providers\Google\Optimization\FleetRoutingStrategy;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\Doubles\TokenFalso;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class FleetRoutingStrategyTest extends TestCase
{
    private const URL = 'https://routeoptimization.googleapis.com/v1/projects/projeto-de-teste:optimizeTours';

    private function estrategia(): FleetRoutingStrategy
    {
        return new FleetRoutingStrategy(
            $this->app->make(MapsHttpClient::class),
            new TokenFalso(),
            self::URL,
        );
    }

    private function ponto(float $offset): Coordinates
    {
        return new Coordinates(-23.5 - $offset, -46.6 - $offset);
    }

    private function requisicao(
        OptimizationObjective $objetivo = OptimizationObjective::MinDistance,
        bool $comDestino = true,
    ): OptimizeWaypointsRequest {
        return new OptimizeWaypointsRequest(
            origin: $this->ponto(0),
            destination: $comDestino ? $this->ponto(0.9) : null,
            intermediates: [$this->ponto(0.1), $this->ponto(0.2), $this->ponto(0.3)],
            objective: $objetivo,
        );
    }

    private function respostaValida(): array
    {
        return [
            'routes' => [[
                'visits' => [
                    ['shipmentIndex' => 2],
                    ['shipmentIndex' => 0],
                    ['shipmentIndex' => 1],
                ],
            ]],
            'metrics' => [
                'aggregatedRouteMetrics' => [
                    'travelDistanceMeters' => 12345,
                    'travelDuration' => '678s',
                ],
            ],
        ];
    }

    public function test_le_ordem_e_totais_da_resposta(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response($this->respostaValida(), 200)]);

        $resultado = $this->estrategia()->optimize($this->requisicao());

        $this->assertSame([2, 0, 1], $resultado->order);
        $this->assertSame(12345, $resultado->distance->meters);
        $this->assertSame(678, $resultado->duration->seconds);
        $this->assertSame('google.fleet_routing', $resultado->strategy);
    }

    public function test_objetivo_vira_custo_no_payload(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response($this->respostaValida(), 200)]);

        $this->estrategia()->optimize($this->requisicao(OptimizationObjective::MinDistance));

        Http::assertSent(function ($request): bool {
            $veiculo = $request->data()['model']['vehicles'][0];

            $this->assertSame(1, $veiculo['costPerKilometer']);
            $this->assertArrayNotHasKey('costPerHour', $veiculo);
            $this->assertSame('Bearer token-de-teste', $request->header('Authorization')[0]);

            return true;
        });
    }

    public function test_objetivo_de_tempo_troca_o_custo(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response($this->respostaValida(), 200)]);

        $this->estrategia()->optimize($this->requisicao(OptimizationObjective::MinTravelTime));

        Http::assertSent(function ($request): bool {
            $veiculo = $request->data()['model']['vehicles'][0];

            $this->assertSame(1, $veiculo['costPerHour']);
            $this->assertArrayNotHasKey('costPerKilometer', $veiculo);

            return true;
        });
    }

    public function test_tour_aberto_omite_endLocation(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response($this->respostaValida(), 200)]);

        $this->estrategia()->optimize($this->requisicao(comDestino: false));

        Http::assertSent(function ($request): bool {
            $veiculo = $request->data()['model']['vehicles'][0];

            $this->assertArrayHasKey('startLocation', $veiculo);
            $this->assertArrayNotHasKey('endLocation', $veiculo, 'tour aberto termina onde a otimizacao deixar');

            return true;
        });
    }

    public function test_deliveries_vai_como_lista(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response($this->respostaValida(), 200)]);

        $this->estrategia()->optimize($this->requisicao());

        Http::assertSent(function ($request): bool {
            $shipments = $request->data()['model']['shipments'];

            $this->assertCount(3, $shipments);
            // `deliveries` e campo repeated no proto: mapa no lugar de lista e
            // payload malformado. O legado monta como mapa, e esse caminho nunca
            // foi exercitado em producao.
            $this->assertArrayHasKey(0, $shipments[0]['deliveries']);
            $this->assertArrayHasKey('arrivalLocation', $shipments[0]['deliveries'][0]);

            return true;
        });
    }

    public function test_ordem_incompleta_e_erro(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response([
            'routes' => [['visits' => [['shipmentIndex' => 0], ['shipmentIndex' => 1]]]],
            'metrics' => ['aggregatedRouteMetrics' => ['travelDistanceMeters' => 1, 'travelDuration' => '1s']],
        ], 200)]);

        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/2 de 3/');

        $this->estrategia()->optimize($this->requisicao());
    }

    public function test_resposta_sem_rota_e_erro(): void
    {
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response(['routes' => []], 200)]);

        $this->expectException(ProviderRequestException::class);

        $this->estrategia()->optimize($this->requisicao());
    }

    public function test_total_zerado_nao_e_erro(): void
    {
        // O legado lancava quando a distancia era zero. E guarda por procuracao:
        // o invariante e "a resposta descreve a rota", nao "a distancia e positiva".
        Http::fake(['routeoptimization.googleapis.com/*' => Http::response([
            'routes' => [['visits' => [['shipmentIndex' => 0], ['shipmentIndex' => 1], ['shipmentIndex' => 2]]]],
            'metrics' => ['aggregatedRouteMetrics' => ['travelDuration' => '0s']],
        ], 200)]);

        $resultado = $this->estrategia()->optimize($this->requisicao());

        $this->assertSame(0, $resultado->distance->meters);
    }
}
