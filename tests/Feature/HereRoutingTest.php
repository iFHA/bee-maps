<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\Events\MapRequestCompleted;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

final class HereRoutingTest extends TestCase
{
    private function fake(): void
    {
        Http::fake([
            'router.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/route-uma-secao.json'), true),
                200,
            ),
        ]);
    }

    public function test_calcula_rota_e_devolve_dto_tipado(): void
    {
        $this->fake();

        $rota = $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route(new RouteRequest(
                new Coordinates(50.10228, 8.69821),
                new Coordinates(50.09878, 8.68752),
                includePolyline: true,
                includeLegs: true,
            ));

        $this->assertSame(5200, $rota->distance->meters);
        $this->assertSame(780, $rota->duration->seconds);
        $this->assertCount(4, $rota->polyline->coordinates());

        Http::assertSent(function ($request): bool {
            $url = urldecode($request->url());

            $this->assertStringContainsString('transportMode=car', $url);
            $this->assertStringContainsString('return=summary,polyline', $url);
            $this->assertStringContainsString('apiKey=chave-here-de-teste', $url);

            return true;
        });
    }

    public function test_rota_sem_otimizacao_e_uma_unica_chamada_upstream(): void
    {
        Event::fake([MapRequestCompleted::class]);
        $this->fake();

        $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route(new RouteRequest(new Coordinates(50.10228, 8.69821), new Coordinates(50.09878, 8.68752)));

        Event::assertDispatchedTimes(MapRequestCompleted::class, 1);
        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $evento) => $evento->service === Service::Routing
                && $evento->upstreamCalls === 1,
        );
    }

    private function fakeOtimizado(): void
    {
        Http::fake([
            'wps.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/findsequence.json'), true),
                200,
            ),
            'router.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/route.json'), true),
                200,
            ),
        ]);
    }

    private function requisicaoOtimizada(): RouteRequest
    {
        return new RouteRequest(
            new Coordinates(50.10228, 8.69821),
            new Coordinates(50.09878, 8.68752),
            [new Coordinates(50.1001, 8.69), new Coordinates(50.10063, 8.6915)],
            TravelMode::Drive,
            optimizeIntermediates: true,
            includeLegs: true,
        );
    }

    public function test_rota_otimizada_consulta_a_sequencia_e_depois_a_rota(): void
    {
        $this->fakeOtimizado();

        $rota = $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route($this->requisicaoOtimizada());

        $this->assertSame([1, 0], $rota->optimizedOrder);
        $this->assertSame(12400, $rota->distance->meters);

        // A rota tem que ser pedida na ordem que o findsequence devolveu.
        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'router.hereapi.com')) {
                return false;
            }

            $url = urldecode($request->url());
            $posicaoSegundo = strpos($url, 'via=50.1006300,8.6915000');
            $posicaoPrimeiro = strpos($url, 'via=50.1001000,8.6900000');

            $this->assertNotFalse($posicaoSegundo);
            $this->assertNotFalse($posicaoPrimeiro);
            $this->assertLessThan($posicaoPrimeiro, $posicaoSegundo);

            return true;
        });
    }

    public function test_rota_otimizada_e_uma_operacao_com_duas_chamadas_upstream(): void
    {
        Event::fake([MapRequestCompleted::class]);
        $this->fakeOtimizado();

        $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route($this->requisicaoOtimizada());

        // Um unico evento: sem isso, a POC compararia uma chamada do Google
        // contra duas do HERE e concluiria o oposto do que os dados dizem.
        Event::assertDispatchedTimes(MapRequestCompleted::class, 1);
        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $evento) => $evento->service === Service::Routing
                && $evento->upstreamCalls === 2,
        );
    }

    public function test_otimizacao_sem_intermediarios_nao_chama_o_findsequence(): void
    {
        // So o router responde: se o findsequence for chamado,
        // preventStrayRequests quebra o teste.
        $this->fake();

        $rota = $this->app->make(MapServiceFactory::class)
            ->routing(Provider::Here)
            ->route(new RouteRequest(
                new Coordinates(50.10228, 8.69821),
                new Coordinates(50.09878, 8.68752),
                optimizeIntermediates: true,
            ));

        $this->assertSame([], $rota->optimizedOrder);
    }
}
