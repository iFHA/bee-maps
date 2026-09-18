<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Http;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderAuthenticationException;
use BeeDelivery\BeeMaps\Exceptions\ProviderRateLimitException;
use BeeDelivery\BeeMaps\Exceptions\ProviderUnavailableException;
use BeeDelivery\BeeMaps\Support\Events\MapRequestCompleted;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

final class MapsHttpClientTest extends TestCase
{
    private function client(): MapsHttpClient
    {
        return $this->app->make(MapsHttpClient::class);
    }

    public function test_get_devolve_o_json_decodificado(): void
    {
        Http::fake(['exemplo.test/*' => Http::response(['ok' => true], 200)]);

        $resposta = $this->client()->get(Provider::Here, Service::Geocoding, 'https://exemplo.test/x', ['q' => 'rua']);

        $this->assertSame(['ok' => true], $resposta);
    }

    public function test_dispara_evento_com_provider_servico_e_status(): void
    {
        Event::fake([MapRequestCompleted::class]);
        Http::fake(['exemplo.test/*' => Http::response(['ok' => true], 200)]);

        $this->client()->get(Provider::Here, Service::Geocoding, 'https://exemplo.test/x');

        Event::assertDispatched(MapRequestCompleted::class, function (MapRequestCompleted $e): bool {
            return $e->provider === Provider::Here
                && $e->service === Service::Geocoding
                && $e->httpStatus === 200
                && $e->upstreamCalls === 1;
        });
    }

    public function test_401_vira_excecao_de_autenticacao(): void
    {
        Http::fake(['exemplo.test/*' => Http::response(['error' => 'bad key'], 401)]);

        $this->expectException(ProviderAuthenticationException::class);

        $this->client()->get(Provider::Google, Service::Geocoding, 'https://exemplo.test/x');
    }

    public function test_429_vira_excecao_de_rate_limit(): void
    {
        Http::fake(['exemplo.test/*' => Http::response([], 429)]);

        $this->expectException(ProviderRateLimitException::class);

        $this->client()->get(Provider::Google, Service::Geocoding, 'https://exemplo.test/x');
    }

    public function test_500_vira_excecao_de_indisponibilidade(): void
    {
        Http::fake(['exemplo.test/*' => Http::response([], 503)]);

        $this->expectException(ProviderUnavailableException::class);

        $this->client()->post(Provider::Google, Service::Autocomplete, 'https://exemplo.test/x', ['input' => 'a']);
    }
}
