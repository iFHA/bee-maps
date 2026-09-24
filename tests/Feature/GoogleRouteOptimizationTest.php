<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ConfigurationException;
use BeeDelivery\BeeMaps\Exceptions\MatrixTooLargeException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\Events\MapRequestCompleted;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

final class GoogleRouteOptimizationTest extends TestCase
{
    private function point(float $offset): Coordinates
    {
        return new Coordinates(-23.5 - $offset, -46.6 - $offset);
    }

    private function request(OptimizationObjective $objective): OptimizeWaypointsRequest
    {
        return new OptimizeWaypointsRequest(
            origin: $this->point(0),
            destination: $this->point(0.9),
            intermediates: [$this->point(0.1), $this->point(0.2), $this->point(0.3)],
            objective: $objective,
        );
    }

    public function test_objetivo_de_tempo_vai_para_o_computeRoutes(): void
    {
        Http::fake(['routes.googleapis.com/directions/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/route-optimized.json'), true),
            200,
        )]);

        $result = $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Google)
            ->optimize($this->request(OptimizationObjective::MinTravelTime));

        $this->assertSame('google.routes', $result->strategy);
        $this->assertSame([2, 0, 1], $result->order);
    }

    public function test_objetivo_de_distancia_vai_para_a_matriz_por_default(): void
    {
        // Sem Http::fake para o computeRoutes: se o seletor mandar o MinDistance
        // para la, preventStrayRequests quebra o teste em vez de passar por acaso.
        Http::fake(['routes.googleapis.com/distanceMatrix/*' => Http::response(
            $this->squareMatrix(5),
            200,
        )]);

        $result = $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Google)
            ->optimize($this->request(OptimizationObjective::MinDistance));

        $this->assertSame('google.matrix_tsp', $result->strategy);
        $this->assertCount(3, $result->order);
    }

    public function test_fleet_routing_sem_a_dependencia_diz_o_comando_exato(): void
    {
        // Sem google/apiclient instalado (o pacote so o sugere), pedir a
        // estrategia tem que falhar dizendo a saida — inclusive a de nao trocar.
        if (class_exists(\Google\Client::class)) {
            $this->markTestSkipped('google/apiclient esta instalado neste ambiente.');
        }

        $this->app['config']->set('bee-maps.google.route_optimization.min_distance_api', 'fleet_routing');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/composer require google\/apiclient/');
        $this->expectExceptionMessageMatches('/matrix_tsp/');

        // A falha e de QUEM PEDE MinDistance, nao de quem obtem o servico: a
        // estrategia so e construida quando o objetivo a seleciona.
        $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Google)
            ->optimize($this->request(OptimizationObjective::MinDistance));
    }

    public function test_fleet_routing_mal_configurado_nao_derruba_o_objetivo_de_tempo(): void
    {
        // MinTravelTime nunca toca a Fleet Routing. Construir as duas estrategias
        // antes de saber qual sera usada fazia um typo no .env derrubar tambem o
        // caminho que nao depende de credencial nenhuma.
        if (class_exists(\Google\Client::class)) {
            $this->markTestSkipped('google/apiclient esta instalado neste ambiente.');
        }

        $this->app['config']->set('bee-maps.google.route_optimization.min_distance_api', 'fleet_routing');

        Http::fake(['routes.googleapis.com/directions/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/route-optimized.json'), true),
            200,
        )]);

        $result = $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Google)
            ->optimize($this->request(OptimizationObjective::MinTravelTime));

        $this->assertSame('google.routes', $result->strategy);
    }

    public function test_matriz_e_tsp_reportam_como_route_optimization(): void
    {
        // A estrategia delega para o contrato RouteMatrix, que emite o evento em
        // nome dele. Sem agrupar, uma otimizacao por distancia no Google some das
        // metricas de RouteOptimization e infla as de RouteMatrix — e a
        // comparacao de latencia entre providers le isso como "o Google nao
        // otimiza". E para isso que o operacao() do MapsHttpClient existe.
        Http::fake(['routes.googleapis.com/distanceMatrix/*' => Http::response($this->squareMatrix(5), 200)]);

        $events = [];

        Event::listen(MapRequestCompleted::class, function (MapRequestCompleted $event) use (&$events): void {
            $events[] = $event;
        });

        $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Google)
            ->optimize($this->request(OptimizationObjective::MinDistance));

        $this->assertCount(1, $events, 'a otimizacao e UMA operacao logica');
        $this->assertSame(Service::RouteOptimization, $events[0]->service);
        $this->assertSame(Provider::Google, $events[0]->provider);
        $this->assertSame(1, $events[0]->upstreamCalls);
    }

    public function test_min_distance_api_desconhecido_cai_no_default(): void
    {
        $this->app['config']->set('bee-maps.google.route_optimization.min_distance_api', 'banana');

        Http::fake(['routes.googleapis.com/distanceMatrix/*' => Http::response($this->squareMatrix(5), 200)]);

        $result = $this->app->make(MapServiceFactory::class)
            ->routeOptimization(Provider::Google)
            ->optimize($this->request(OptimizationObjective::MinDistance));

        $this->assertSame('google.matrix_tsp', $result->strategy);
    }

    public function test_falha_antes_da_rede_nao_emite_evento_de_operacao(): void
    {
        // 24 paradas => 26 pontos => 676 elementos, acima dos 625 do Google. A
        // guarda dispara antes de sair para a rede: o agrupamento nao pode
        // inventar uma operacao que nunca chegou a acontecer.
        $stops = [];

        for ($i = 0; $i < 24; $i++) {
            $stops[] = $this->point(0.01 * ($i + 1));
        }

        $events = [];

        Event::listen(MapRequestCompleted::class, function (MapRequestCompleted $event) use (&$events): void {
            $events[] = $event;
        });

        try {
            $this->app->make(MapServiceFactory::class)
                ->routeOptimization(Provider::Google)
                ->optimize(new OptimizeWaypointsRequest(
                    origin: $this->point(0),
                    destination: $this->point(0.9),
                    intermediates: $stops,
                    objective: OptimizationObjective::MinDistance,
                ));

            $this->fail('a guarda de tamanho da matriz devia ter disparado');
        } catch (MatrixTooLargeException $e) {
            $this->assertStringContainsString('676', $e->getMessage());
        }

        $this->assertSame([], $events, 'sem chamada upstream, sem evento');
    }

    /**
     * Matriz NxN onde a distancia entre i e j e |i - j| * 1000 metros: os pontos
     * ficam numa reta na ordem em que foram enviados.
     */
    private function squareMatrix(int $n): array
    {
        $elements = [];

        for ($o = 0; $o < $n; $o++) {
            for ($d = 0; $d < $n; $d++) {
                $meters = abs($o - $d) * 1000;

                $elements[] = [
                    'originIndex' => $o,
                    'destinationIndex' => $d,
                    'distanceMeters' => $meters,
                    'duration' => ($meters * 6) . 's',
                    'condition' => 'ROUTE_EXISTS',
                ];
            }
        }

        return $elements;
    }
}
