<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class HereRouteOptimizationTest extends TestCase
{
    private function fake(): void
    {
        Http::fake(['wps.hereapi.com/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/findsequence-otimizacao.json'), true),
            200,
        )]);
    }

    private function requisicao(?Coordinates $destino, OptimizationObjective $objetivo): OptimizeWaypointsRequest
    {
        return new OptimizeWaypointsRequest(
            origin: new Coordinates(-23.5615, -46.6562),
            destination: $destino,
            intermediates: [
                new Coordinates(-23.5505, -46.6333),
                new Coordinates(-23.587, -46.657),
                new Coordinates(-23.532, -46.639),
            ],
            objective: $objetivo,
        );
    }

    public function test_devolve_ordem_e_totais_numa_chamada_so(): void
    {
        $this->fake();

        $resultado = $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Here)
            ->optimize($this->requisicao(new Coordinates(-23.598, -46.686), OptimizationObjective::MinDistance));

        // A fixture visita destination3, destination1, destination2 — que sao os
        // intermediarios 2, 0 e 1.
        $this->assertSame([2, 0, 1], $resultado->order);
        $this->assertSame(23787, $resultado->distance->meters);
        $this->assertSame(3058, $resultado->duration->seconds);
        $this->assertSame('here.findsequence', $resultado->strategy);

        // Diferente do Routing, aqui NAO ha segunda chamada ao /v8/routes: o
        // contrato pede ordem e totais, e o findsequence2 devolve os dois.
        Http::assertSentCount(1);
    }

    public function test_objetivo_vai_na_query(): void
    {
        $this->fake();

        $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Here)
            ->optimize($this->requisicao(new Coordinates(-23.598, -46.686), OptimizationObjective::MinTravelTime));

        Http::assertSent(function ($request): bool {
            $this->assertStringContainsString('improveFor=time', $request->url());

            return true;
        });
    }

    public function test_tour_aberto_nao_manda_end(): void
    {
        $this->fake();

        $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Here)
            ->optimize($this->requisicao(null, OptimizationObjective::MinDistance));

        Http::assertSent(function ($request): bool {
            $this->assertStringNotContainsString('end=', $request->url());

            return true;
        });
    }

    public function test_resposta_inutilizavel_vira_excecao_de_provider(): void
    {
        // Mesmo evento do lado Google: 200 com corpo que nao descreve resposta
        // usavel. Tem que ser a MESMA classe de excecao nos dois, senao quem
        // escreve `catch (ProviderRequestException)` pega um provider e deixa o
        // outro escapar.
        Http::fake(['wps.hereapi.com/*' => Http::response(['results' => []], 200)]);

        try {
            $this->app->make(MapServiceFactory::class)
                ->routeOptimization(Provider::Here)
                ->optimize($this->requisicao(new Coordinates(-23.598, -46.686), OptimizationObjective::MinDistance));

            $this->fail('resposta sem resultado devia ter lancado');
        } catch (ProviderRequestException $e) {
            $this->assertSame(Provider::Here, $e->provider());
            $this->assertSame(Service::RouteOptimization, $e->service());
        }
    }

    public function test_ordem_incompleta_vira_excecao_de_provider(): void
    {
        Http::fake(['wps.hereapi.com/*' => Http::response(['results' => [[
            'waypoints' => [
                ['id' => 'start', 'sequence' => 0],
                ['id' => 'destination1', 'sequence' => 1],
                ['id' => 'destination2', 'sequence' => 2],
                ['id' => 'end', 'sequence' => 3],
            ],
            'distance' => '1000',
            'time' => '100',
        ]]], 200)]);

        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/2 de 3/');

        $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Here)
            ->optimize($this->requisicao(new Coordinates(-23.598, -46.686), OptimizationObjective::MinDistance));
    }

    public function test_totais_ausentes_viram_excecao_de_provider(): void
    {
        Http::fake(['wps.hereapi.com/*' => Http::response(['results' => [[
            'waypoints' => [
                ['id' => 'start', 'sequence' => 0],
                ['id' => 'destination1', 'sequence' => 1],
                ['id' => 'destination2', 'sequence' => 2],
                ['id' => 'destination3', 'sequence' => 3],
                ['id' => 'end', 'sequence' => 4],
            ],
        ]]], 200)]);

        $this->expectException(ProviderRequestException::class);

        $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Here)
            ->optimize($this->requisicao(new Coordinates(-23.598, -46.686), OptimizationObjective::MinDistance));
    }
}
