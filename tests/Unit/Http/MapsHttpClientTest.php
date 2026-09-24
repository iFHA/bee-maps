<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Http;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderAuthenticationException;
use BeeDelivery\BeeMaps\Exceptions\ProviderRateLimitException;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Exceptions\ProviderUnavailableException;
use BeeDelivery\BeeMaps\Support\Events\MapRequestCompleted;
use BeeDelivery\BeeMaps\Support\Http\MapsHttpClient;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

final class MapsHttpClientTest extends TestCase
{
    private function client(): MapsHttpClient
    {
        return $this->app->make(MapsHttpClient::class);
    }

    public function test_get_returns_the_decoded_json(): void
    {
        Http::fake(['exemplo.test/*' => Http::response(['ok' => true], 200)]);

        $response = $this->client()->get(Provider::Here, Service::Geocoding, 'https://exemplo.test/x', ['q' => 'rua']);

        $this->assertSame(['ok' => true], $response);
    }

    public function test_an_array_query_value_becomes_a_repeated_key_instead_of_an_indexed_one(): void
    {
        Http::fake(['exemplo.test/*' => Http::response(['ok' => true], 200)]);

        $this->client()->get(
            Provider::Here,
            Service::Autocomplete,
            'https://exemplo.test/x',
            ['in' => ['circle:1,2;r=3000', 'countryCode:BRA'], 'q' => 'rua'],
        );

        Http::assertSent(function ($request): bool {
            $url = urldecode($request->url());

            $this->assertStringNotContainsString('in[0]=', $url);
            $this->assertStringNotContainsString('in[1]=', $url);
            $this->assertStringContainsString('in=circle:1,2;r=3000', $url);
            $this->assertStringContainsString('in=countryCode:BRA', $url);
            $this->assertStringContainsString('q=rua', $url);

            return true;
        });
    }

    public function test_fires_an_event_with_provider_service_and_status(): void
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

    public function test_401_becomes_an_authentication_exception(): void
    {
        Http::fake(['exemplo.test/*' => Http::response(['error' => 'bad key'], 401)]);

        $this->expectException(ProviderAuthenticationException::class);

        $this->client()->get(Provider::Google, Service::Geocoding, 'https://exemplo.test/x');
    }

    public function test_401_is_not_retried(): void
    {
        Http::fake(['exemplo.test/*' => Http::response(['error' => 'bad key'], 401)]);

        try {
            $this->client()->get(Provider::Google, Service::Geocoding, 'https://exemplo.test/x');
            $this->fail('Esperava ProviderAuthenticationException');
        } catch (ProviderAuthenticationException) {
            // esperado
        }

        Http::assertSentCount(1);
    }

    public function test_429_becomes_a_rate_limit_exception(): void
    {
        Http::fake(['exemplo.test/*' => Http::response([], 429)]);

        $this->expectException(ProviderRateLimitException::class);

        $this->client()->get(Provider::Google, Service::Geocoding, 'https://exemplo.test/x');
    }

    public function test_500_becomes_an_unavailable_exception(): void
    {
        Http::fake(['exemplo.test/*' => Http::response([], 503)]);

        $this->expectException(ProviderUnavailableException::class);

        $this->client()->post(Provider::Google, Service::Autocomplete, 'https://exemplo.test/x', ['input' => 'a']);
    }

    public function test_preserves_null_when_the_provider_sends_no_code(): void
    {
        Http::fake(['exemplo.test/*' => Http::response([], 429)]);

        try {
            $this->client()->get(Provider::Google, Service::Geocoding, 'https://exemplo.test/x');
            $this->fail('Esperava ProviderRateLimitException');
        } catch (ProviderRateLimitException $e) {
            $this->assertNull($e->providerCode());
        }
    }

    public function test_converts_the_code_to_a_string_when_the_provider_sends_one(): void
    {
        Http::fake(['exemplo.test/*' => Http::response([
            'error' => [
                'status' => 'PERMISSION_DENIED',
                'message' => 'chave sem permissao',
            ],
        ], 403)]);

        try {
            $this->client()->get(Provider::Google, Service::Geocoding, 'https://exemplo.test/x');
            $this->fail('Esperava ProviderAuthenticationException');
        } catch (ProviderAuthenticationException $e) {
            $this->assertSame('PERMISSION_DENIED', $e->providerCode());
            $this->assertStringContainsString('chave sem permissao', $e->getMessage());
        }
    }

    public function test_a_connection_failure_does_not_leak_the_credential_in_the_exception_message(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: timed out for https://exemplo.test/v1/x?q=rua&apiKey=SEGREDO_NAO_PODE_VAZAR');
        });

        try {
            $this->client()->get(Provider::Here, Service::Autocomplete, 'https://exemplo.test/v1/x', ['q' => 'rua', 'apiKey' => 'SEGREDO_NAO_PODE_VAZAR']);
            $this->fail('Esperava ProviderUnavailableException');
        } catch (ProviderUnavailableException $e) {
            $this->assertStringNotContainsString('SEGREDO_NAO_PODE_VAZAR', $e->getMessage());
            $this->assertStringContainsString('apiKey=[REDACTED]', $e->getMessage());
        }
    }

    public function test_a_connection_failure_fires_an_event_with_status_zero(): void
    {
        Event::fake([MapRequestCompleted::class]);
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: timed out for https://exemplo.test/v1/x');
        });

        try {
            $this->client()->get(Provider::Here, Service::Autocomplete, 'https://exemplo.test/v1/x');
            $this->fail('Esperava ProviderUnavailableException');
        } catch (ProviderUnavailableException) {
            // esperado
        }

        Event::assertDispatched(MapRequestCompleted::class, function (MapRequestCompleted $e): bool {
            return $e->provider === Provider::Here
                && $e->service === Service::Autocomplete
                && $e->httpStatus === 0;
        });
    }

    public function test_a_connection_failure_preserves_a_parameter_that_only_contains_a_keyword_as_a_substring(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: timed out for https://exemplo.test/v1/x?monkey=banana');
        });

        try {
            $this->client()->get(Provider::Here, Service::Autocomplete, 'https://exemplo.test/v1/x', ['monkey' => 'banana']);
            $this->fail('Esperava ProviderUnavailableException');
        } catch (ProviderUnavailableException $e) {
            $this->assertStringContainsString('monkey=banana', $e->getMessage());
        }
    }

    public function test_a_google_error_wrapped_in_an_array_preserves_message_and_code(): void
    {
        // Corpo real do computeRouteMatrix quando a matriz passa de 625 elementos.
        Http::fake(['matriz.exemplo.test/*' => Http::response([[
            'error' => [
                'code' => 400,
                'message' => 'Request exceeded the maximum number of elements.',
                'status' => 'INVALID_ARGUMENT',
            ],
        ]], 400)]);

        try {
            $this->app->make(MapsHttpClient::class)->post(
                Provider::Google,
                Service::RouteMatrix,
                'https://matriz.exemplo.test/x',
                [],
            );

            $this->fail('Esperava ProviderRequestException.');
        } catch (ProviderRequestException $e) {
            $this->assertStringContainsString('maximum number of elements', $e->getMessage());
            $this->assertSame('INVALID_ARGUMENT', $e->providerCode());
        }
    }
}
