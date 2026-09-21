<?php

namespace BeeDelivery\BeeMaps\Support\Http;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderAuthenticationException;
use BeeDelivery\BeeMaps\Exceptions\ProviderRateLimitException;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Exceptions\ProviderUnavailableException;
use BeeDelivery\BeeMaps\Support\Events\MapRequestCompleted;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

final class MapsHttpClient
{
    public function __construct(
        private readonly Factory $http,
        private readonly Dispatcher $events,
        private readonly array $config,
    ) {
    }

    public function get(Provider $provider, Service $service, string $url, array $query = [], array $headers = []): array
    {
        return $this->send($provider, $service, fn () => $this->pending($headers)->get($url, $query));
    }

    public function post(Provider $provider, Service $service, string $url, array $payload, array $headers = []): array
    {
        return $this->send($provider, $service, fn () => $this->pending($headers)->post($url, $payload));
    }

    private function pending(array $headers)
    {
        return $this->http
            ->withHeaders($headers + ['Accept' => 'application/json'])
            ->timeout($this->config['timeout'])
            ->connectTimeout($this->config['connect_timeout'])
            ->retry($this->config['retries'], $this->config['retry_delay_ms'], throw: false);
    }

    private function send(Provider $provider, Service $service, callable $call): array
    {
        $inicio = microtime(true);

        try {
            /** @var Response $resposta */
            $resposta = $call();
        } catch (ConnectionException $e) {
            throw new ProviderUnavailableException($provider, $service, $this->redigirCredenciais($e->getMessage()));
        }

        $this->events->dispatch(new MapRequestCompleted(
            provider: $provider,
            service: $service,
            httpStatus: $resposta->status(),
            durationMs: round((microtime(true) - $inicio) * 1000, 2),
        ));

        if ($resposta->failed()) {
            throw $this->traduzirErro($provider, $service, $resposta);
        }

        return $resposta->json() ?? [];
    }

    private function traduzirErro(Provider $provider, Service $service, Response $resposta): ProviderRequestException
    {
        $status = $resposta->status();
        $corpo = $resposta->json();
        $codigo = $corpo['error']['status'] ?? $corpo['error']['code'] ?? null;
        $mensagem = $this->redigirCredenciais($corpo['error']['message'] ?? $resposta->reason() ?? 'falha na chamada ao provider');

        // Preserva null: providerCode() é ?string justamente para distinguir
        // "o provider não mandou código" de "mandou um código". Um (string) aqui
        // transformaria todo ausente em '' e quebraria essa distinção.
        $codigo = $codigo !== null ? (string) $codigo : null;

        return match (true) {
            $status === 401, $status === 403 => new ProviderAuthenticationException($provider, $service, $mensagem, $status, $codigo),
            $status === 429 => new ProviderRateLimitException($provider, $service, $mensagem, $status, $codigo),
            $status >= 500 => new ProviderUnavailableException($provider, $service, $mensagem, $status, $codigo),
            default => new ProviderRequestException($provider, $service, $mensagem, $status, $codigo),
        };
    }

    /**
     * Redige valores de parâmetros de query que parecem credenciais (ex.: apiKey, token)
     * em qualquer texto que possa conter a URL da requisição, mantendo o nome do
     * parâmetro visível para não perder capacidade de diagnóstico.
     *
     * Guzzle só redige a senha em `user:pass@host` (Psr7\Utils::redactUserInfo); query
     * strings com `apiKey=...` (HERE) ou `key=...` (Google) passam intactas para
     * mensagens de exceção e, dali, para logs.
     */
    private function redigirCredenciais(string $texto): string
    {
        $parametrosCredencial = 'apiKey|api_key|key|token|access_token|signature|sig';

        return preg_replace(
            "/([?&])({$parametrosCredencial})=[^&\\s]*/i",
            '$1$2=[REDACTED]',
            $texto,
        );
    }
}
