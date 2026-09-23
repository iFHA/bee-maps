<?php

namespace BeeDelivery\BeeMaps\Tests\Live;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\DTOs\Requests\PlaceSearchRequest;
use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Bate na API real. Roda a mao:
 *
 *   GOOGLE_MAPS_KEY=... HERE_API_KEY=... vendor/bin/phpunit --group live
 *
 * Nao asserta valores — asserta que a chamada FUNCIONA: endpoint existe, chave
 * tem acesso, contrato volta preenchido. E a unica camada que pega credencial
 * sem escopo, endpoint renomeado e SKU nao habilitado.
 */
#[Group('live')]
final class SmokeLiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A TestCase base bloqueia requisicao real; aqui e o oposto do resto
        // da suite — o ponto e justamente sair para a rede.
        Http::allowStrayRequests();
    }

    protected function defineEnvironment($app): void
    {
        $google = getenv('GOOGLE_MAPS_KEY') ?: null;
        $here = getenv('HERE_API_KEY') ?: null;

        if ($google === null || $here === null) {
            $this->markTestSkipped('Defina GOOGLE_MAPS_KEY e HERE_API_KEY para rodar o smoke live.');
        }

        $app['config']->set('bee-maps.google.key', $google);
        $app['config']->set('bee-maps.here.api_key', $here);

        // O caso "sem coordenada" depende deste centro: o Autosuggest do HERE
        // recusa a chamada sem foco espacial.
        $app['config']->set('bee-maps.here.autosuggest_center', '-23.5615,-46.6562');
    }

    public static function providers(): array
    {
        return [
            'google' => [Provider::Google],
            'here' => [Provider::Here],
        ];
    }

    #[DataProvider('providers')]
    public function test_autocomplete_sem_coordenada_responde(Provider $provider): void
    {
        $colecao = $this->app->make(MapServiceFactory::class)
            ->autocomplete($provider)
            ->suggest(new AutocompleteRequest('Avenida Paulista'));

        $this->assertGreaterThan(0, $colecao->count());
        $this->assertNotSame('', $colecao->first()->description);
    }

    #[DataProvider('providers')]
    public function test_autocomplete_com_coordenada_e_raio_default_responde(Provider $provider): void
    {
        // Esta e a chamada que dava 400 no HERE: near presente e radiusMeters no
        // default de 50000. Nenhum teste com Http::fake pega isso, porque o fake
        // nao valida a query.
        $colecao = $this->app->make(MapServiceFactory::class)
            ->autocomplete($provider)
            ->suggest(new AutocompleteRequest('Avenida Paulista', new Coordinates(-23.5615, -46.6562)));

        $this->assertGreaterThan(0, $colecao->count());
    }

    #[DataProvider('providers')]
    public function test_autocomplete_com_coordenada_e_sem_raio_responde(Provider $provider): void
    {
        $colecao = $this->app->make(MapServiceFactory::class)
            ->autocomplete($provider)
            ->suggest(new AutocompleteRequest('Avenida Paulista', new Coordinates(-23.5615, -46.6562), null));

        $this->assertGreaterThan(0, $colecao->count());
    }

    #[DataProvider('providers')]
    public function test_geocoding_responde(Provider $provider): void
    {
        $colecao = $this->app->make(MapServiceFactory::class)
            ->geocoding($provider)
            ->geocode('Avenida Paulista 1000, Sao Paulo');

        $this->assertGreaterThan(0, $colecao->count());
        $this->assertNotNull($colecao->first()->coordinates);
    }

    #[DataProvider('providers')]
    public function test_place_search_responde_com_endereco_estruturado(Provider $provider): void
    {
        $colecao = $this->app->make(MapServiceFactory::class)
            ->placeSearch($provider)
            ->search(new PlaceSearchRequest('farmacia', new Coordinates(-23.5615, -46.6562)));

        $this->assertGreaterThan(0, $colecao->count());

        // D16 virando verificacao real: se o SKU Enterprise nao estiver
        // habilitado na conta, o Google devolve 403 ou vem sem componentes, e
        // este assert e o unico lugar onde isso aparece antes da producao.
        $this->assertNotNull($colecao->first()->address->city);
    }

    #[DataProvider('providers')]
    public function test_rota_simples_responde_com_polyline(Provider $provider): void
    {
        $rota = $this->app->make(MapServiceFactory::class)
            ->routing($provider)
            ->route(new RouteRequest(
                origin: new Coordinates(-23.5615, -46.6562),
                destination: new Coordinates(-23.5505, -46.6425),
                includePolyline: true,
            ));

        $this->assertGreaterThan(0, $rota->distance->meters);
        $this->assertGreaterThan(0, $rota->duration->seconds);
        $this->assertNotNull($rota->polyline);
        // Decodificar de verdade: e o que prova que o formato do provider e o
        // que o decodificador registrado espera.
        $this->assertGreaterThan(1, count($rota->polyline->coordinates()));
    }

    /**
     * ESTE e o teste que existe por causa da D18. A URL ja foi confirmada contra
     * a documentacao vigente e por sondagem HTTP; o que falta e o que so a chave
     * real responde: a conta Bee esta provisionada para a Waypoints Sequence API?
     *
     * 401 aqui = a chave nao tem acesso ao servico (provisionamento, nao URL).
     * 404 aqui = a ambiguidade `findsequence2` vs `findsequence.json` caiu para o
     * outro lado; trocar bee-maps.here.endpoints.findsequence e rodar de novo.
     */
    public function test_rota_otimizada_do_here_usa_o_endpoint_de_sequencia_configurado(): void
    {
        $rota = $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route(new RouteRequest(
                origin: new Coordinates(-23.5615, -46.6562),
                destination: new Coordinates(-23.5505, -46.6425),
                intermediates: [
                    new Coordinates(-23.5580, -46.6500),
                    new Coordinates(-23.5540, -46.6470),
                ],
                optimizeIntermediates: true,
                includeLegs: true,
            ));

        $this->assertCount(2, $rota->optimizedOrder);
        $this->assertGreaterThan(0, $rota->distance->meters);
    }

    public function test_rota_otimizada_do_here_em_duas_rodas_responde(): void
    {
        // O smoke so exercitava Drive. O findsequence e um motor legado, e os
        // quatro transport modes nao sao obviamente os mesmos do /v8/routes.
        $rota = $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route(new RouteRequest(
                origin: new Coordinates(-23.5615, -46.6562),
                destination: new Coordinates(-23.5505, -46.6425),
                intermediates: [new Coordinates(-23.5580, -46.6500), new Coordinates(-23.5540, -46.6470)],
                mode: TravelMode::TwoWheeler,
                optimizeIntermediates: true,
            ));

        $this->assertCount(2, $rota->optimizedOrder);
    }

    public function test_rota_otimizada_do_google_resolve_em_uma_chamada(): void
    {
        $rota = $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Google)
            ->route(new RouteRequest(
                origin: new Coordinates(-23.5615, -46.6562),
                destination: new Coordinates(-23.5505, -46.6425),
                intermediates: [
                    new Coordinates(-23.5580, -46.6500),
                    new Coordinates(-23.5540, -46.6470),
                ],
                optimizeIntermediates: true,
                includeLegs: true,
            ));

        $this->assertCount(2, $rota->optimizedOrder);
        $this->assertCount(3, $rota->legs);
    }

    #[DataProvider('providers')]
    public function test_matriz_2x2_responde(Provider $provider): void
    {
        $matriz = $this->app->make(MapServiceFactory::class)
            ->routeMatrix($provider)
            ->matrix(new RouteMatrixRequest(
                [new Coordinates(-23.5615, -46.6562), new Coordinates(-23.5505, -46.6425)],
                [new Coordinates(-23.5580, -46.6500), new Coordinates(-23.5540, -46.6470)],
            ));

        $this->assertCount(4, $matriz);

        // Todos os quatro pares tem que existir e ser alcancaveis: sao pontos a
        // poucos quilometros um do outro em Sao Paulo.
        foreach ([0, 1] as $origem) {
            foreach ([0, 1] as $destino) {
                $entrada = $matriz->entry($origem, $destino);

                $this->assertNotNull($entrada, "Faltou a entrada ({$origem},{$destino}).");
                $this->assertTrue($entrada->reachable);
                $this->assertGreaterThan(0, $entrada->distance->meters);
            }
        }
    }

    public function test_matriz_acima_de_625_elementos_e_recusada_pelo_google(): void
    {
        $this->expectException(\BeeDelivery\BeeMaps\Exceptions\MatrixTooLargeException::class);

        $pontos = [];

        for ($i = 0; $i < 26; $i++) {
            $pontos[] = new Coordinates(-23.5 - ($i / 10000), -46.6 - ($i / 10000));
        }

        $this->app->make(MapServiceFactory::class)
            ->routeMatrix(Provider::Google)
            ->matrix(new RouteMatrixRequest($pontos, $pontos));
    }

    /** @return list<Coordinates> */
    private function paradasReais(): array
    {
        return [
            new Coordinates(-23.5505, -46.6333),
            new Coordinates(-23.5870, -46.6570),
            new Coordinates(-23.5320, -46.6390),
        ];
    }

    /**
     * Alternativas so existem se o provider de fato devolver mais de uma rota —
     * e isso nenhum Http::fake responde.
     */
    #[DataProvider('providers')]
    public function test_live_rota_com_alternativas_devolve_mais_de_uma(Provider $provider): void
    {
        $rota = $this->app->make(MapServiceFactory::class)
            ->routing($provider)
            ->route(new RouteRequest(
                origin: new Coordinates(-23.5615, -46.6562),
                destination: new Coordinates(-23.5505, -46.6333),
                alternatives: 3,
            ));

        $this->assertNotSame([], $rota->alternatives, 'Nenhuma alternativa: o parametro nao chegou no provider.');

        foreach ($rota->alternatives as $alternativa) {
            $this->assertGreaterThan(0, $alternativa->distance->meters);
        }
    }

    #[DataProvider('providers')]
    public function test_live_sem_pedir_alternativas_vem_rota_unica(Provider $provider): void
    {
        $rota = $this->app->make(MapServiceFactory::class)
            ->routing($provider)
            ->route(new RouteRequest(
                origin: new Coordinates(-23.5615, -46.6562),
                destination: new Coordinates(-23.5505, -46.6333),
            ));

        $this->assertSame([], $rota->alternatives);
    }

    #[DataProvider('providers')]
    public function test_live_otimiza_com_fim_fixo(Provider $provider): void
    {
        $resultado = $this->app->make(MapServiceFactory::class)
            ->routeOptimization($provider)
            ->optimize(new OptimizeWaypointsRequest(
                origin: new Coordinates(-23.5615, -46.6562),
                destination: new Coordinates(-23.5980, -46.6860),
                intermediates: $this->paradasReais(),
            ));

        $this->assertCount(3, $resultado->order);
        $this->assertGreaterThan(0, $resultado->distance->meters);
    }

    #[DataProvider('providers')]
    public function test_live_otimiza_tour_aberto(Provider $provider): void
    {
        $resultado = $this->app->make(MapServiceFactory::class)
            ->routeOptimization($provider)
            ->optimize(new OptimizeWaypointsRequest(
                origin: new Coordinates(-23.5615, -46.6562),
                intermediates: $this->paradasReais(),
            ));

        $this->assertCount(3, $resultado->order);
        $this->assertGreaterThan(0, $resultado->distance->meters);
    }

    #[DataProvider('providers')]
    public function test_live_otimiza_com_volta_a_origem(Provider $provider): void
    {
        $origem = new Coordinates(-23.5615, -46.6562);

        $resultado = $this->app->make(MapServiceFactory::class)
            ->routeOptimization($provider)
            ->optimize(new OptimizeWaypointsRequest(
                origin: $origem,
                destination: $origem,
                intermediates: $this->paradasReais(),
            ));

        $this->assertCount(3, $resultado->order);
    }

    #[DataProvider('providers')]
    public function test_live_objetivo_de_distancia_e_aceito(Provider $provider): void
    {
        // No HERE vira improveFor=distance; no Google, a matriz + TSP local.
        // A estrategia fleet_routing NAO entra no smoke: a service account nao
        // existe neste ambiente.
        $resultado = $this->app->make(MapServiceFactory::class)
            ->routeOptimization($provider)
            ->optimize(new OptimizeWaypointsRequest(
                origin: new Coordinates(-23.5615, -46.6562),
                destination: new Coordinates(-23.5980, -46.6860),
                intermediates: $this->paradasReais(),
                objective: OptimizationObjective::MinDistance,
            ));

        $this->assertCount(3, $resultado->order);
        $this->assertGreaterThan(0, $resultado->distance->meters);
    }
}
