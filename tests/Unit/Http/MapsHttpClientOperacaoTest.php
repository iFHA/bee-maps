<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Http;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderUnavailableException;
use BeeDelivery\BeeMaps\Support\Events\MapRequestCompleted;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

final class MapsHttpClientOperacaoTest extends TestCase
{
    public function test_duas_chamadas_dentro_de_uma_operacao_viram_um_unico_evento(): void
    {
        Event::fake([MapRequestCompleted::class]);

        Http::fake([
            'primeira.exemplo.test/*' => Http::response(['ok' => 1], 200),
            'segunda.exemplo.test/*' => Http::response(['ok' => 2], 200),
        ]);

        $cliente = $this->app->make(MapsHttpClient::class);

        $resultado = $cliente->operacao(Provider::Here, Service::Routing, function () use ($cliente) {
            $cliente->get(Provider::Here, Service::Routing, 'https://primeira.exemplo.test/x');

            return $cliente->get(Provider::Here, Service::Routing, 'https://segunda.exemplo.test/y');
        });

        $this->assertSame(['ok' => 2], $resultado);

        Event::assertDispatchedTimes(MapRequestCompleted::class, 1);
        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $evento) => $evento->upstreamCalls === 2
                && $evento->httpStatus === 200
                && $evento->provider === Provider::Here
                && $evento->service === Service::Routing,
        );
    }

    public function test_chamada_fora_de_operacao_continua_disparando_um_evento_por_chamada(): void
    {
        Event::fake([MapRequestCompleted::class]);

        Http::fake(['primeira.exemplo.test/*' => Http::response(['ok' => 1], 200)]);

        $cliente = $this->app->make(MapsHttpClient::class);
        $cliente->get(Provider::Here, Service::Routing, 'https://primeira.exemplo.test/x');
        $cliente->get(Provider::Here, Service::Routing, 'https://primeira.exemplo.test/x');

        Event::assertDispatchedTimes(MapRequestCompleted::class, 2);
        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $evento) => $evento->upstreamCalls === 1,
        );
    }

    public function test_falha_no_meio_da_operacao_ainda_emite_o_evento_e_propaga_a_excecao(): void
    {
        Event::fake([MapRequestCompleted::class]);

        Http::fake([
            'primeira.exemplo.test/*' => Http::response(['ok' => 1], 200),
            'segunda.exemplo.test/*' => Http::response(['error' => ['message' => 'caiu']], 503),
        ]);

        $cliente = $this->app->make(MapsHttpClient::class);

        try {
            $cliente->operacao(Provider::Here, Service::Routing, function () use ($cliente) {
                $cliente->get(Provider::Here, Service::Routing, 'https://primeira.exemplo.test/x');
                $cliente->get(Provider::Here, Service::Routing, 'https://segunda.exemplo.test/y');
            });

            $this->fail('Esperava ProviderUnavailableException.');
        } catch (ProviderUnavailableException $e) {
            $this->assertSame(503, $e->httpStatus());
        }

        // Sem isto, uma operacao que falha na segunda chamada sumiria das
        // metricas — exatamente o caso que mais importa medir.
        Event::assertDispatchedTimes(MapRequestCompleted::class, 1);
        Event::assertDispatched(
            MapRequestCompleted::class,
            fn (MapRequestCompleted $evento) => $evento->upstreamCalls === 2 && $evento->httpStatus === 503,
        );
    }

    public function test_operacao_sem_nenhuma_chamada_nao_emite_evento(): void
    {
        Event::fake([MapRequestCompleted::class]);

        $cliente = $this->app->make(MapsHttpClient::class);

        $cliente->operacao(Provider::Here, Service::Routing, fn () => 'nada a fazer');

        Event::assertNotDispatched(MapRequestCompleted::class);
    }
}
