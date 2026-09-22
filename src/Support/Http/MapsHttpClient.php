<?php

namespace BeeDelivery\BeeMaps\Support\Http;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ProviderAuthenticationException;
use BeeDelivery\BeeMaps\Exceptions\ProviderRateLimitException;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Exceptions\ProviderUnavailableException;
use BeeDelivery\BeeMaps\Support\CredentialRedaction;
use BeeDelivery\BeeMaps\Support\Events\MapRequestCompleted;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

final class MapsHttpClient
{
    public function __construct(
        private readonly Factory $http,
        private readonly Dispatcher $events,
        private readonly array $config,
    ) {
    }

    /**
     * Acumulador da operacao em curso: null fora de operacao(). Guardar estado
     * mutavel num singleton so e aceitavel porque o escopo e sincrono e o
     * finally de operacao() sempre o limpa — inclusive quando a chamada estoura.
     *
     * @var array{calls: int, durationMs: float, status: int}|null
     */
    private ?array $operacaoEmCurso = null;

    public function get(Provider $provider, Service $service, string $url, array $query = [], array $headers = []): array
    {
        return $this->send($provider, $service, fn () => $this->pending($headers)->get($url, $this->serializarQuery($query)));
    }

    public function post(Provider $provider, Service $service, string $url, array $payload, array $headers = []): array
    {
        return $this->send($provider, $service, fn () => $this->pending($headers)->post($url, $payload));
    }

    /**
     * Agrupa varias chamadas HTTP como UMA operacao logica: um unico
     * MapRequestCompleted, com a soma das duracoes, a contagem de chamadas em
     * upstreamCalls e o status da ultima.
     *
     * Existe porque no HERE uma rota otimizada custa duas chamadas upstream
     * (findsequence + /v8/routes). Sem o agrupamento, a comparacao de latencia
     * da POC veria duas chamadas rapidas do HERE contra uma do Google e
     * concluiria o oposto do que os dados dizem.
     */
    public function operacao(Provider $provider, Service $service, callable $passos): mixed
    {
        $anterior = $this->operacaoEmCurso;
        $this->operacaoEmCurso = ['calls' => 0, 'durationMs' => 0.0, 'status' => 0];

        try {
            return $passos();
        } finally {
            $acumulado = $this->operacaoEmCurso;
            $this->operacaoEmCurso = $anterior;

            if ($acumulado['calls'] > 0) {
                $this->events->dispatch(new MapRequestCompleted(
                    provider: $provider,
                    service: $service,
                    httpStatus: $acumulado['status'],
                    durationMs: round($acumulado['durationMs'], 2),
                    upstreamCalls: $acumulado['calls'],
                ));
            }
        }
    }

    private function pending(array $headers): PendingRequest
    {
        return $this->http
            ->withHeaders($headers + ['Accept' => 'application/json'])
            ->timeout($this->config['timeout'])
            ->connectTimeout($this->config['connect_timeout'])
            ->retry($this->config['attempts'], $this->config['retry_delay_ms'], $this->deveTentarNovamente(...), throw: false);
    }

    /**
     * PHP/Guzzle serializam array em query string com indices ("in[0]=a&in[1]=b"),
     * formato que a maioria das APIs de terceiros nao aceita para repetir uma
     * mesma chave (ex.: o filtro "in" do HERE precisa de "in=a&in=b"). Serializa
     * manualmente cada valor de array como a mesma chave repetida.
     */
    private function serializarQuery(array $parametros): string
    {
        $partes = [];

        foreach ($parametros as $chave => $valor) {
            foreach ((array) $valor as $item) {
                $partes[] = rawurlencode((string) $chave) . '=' . rawurlencode((string) $item);
            }
        }

        return implode('&', $partes);
    }

    /**
     * So vale a pena tentar de novo em falha de conexao (timeout, DNS, etc.) ou em
     * resposta que sinaliza indisponibilidade temporaria (429, 5xx). Repetir um 401/403
     * apenas dobra a carga no provider exatamente quando ele esta bloqueando a chamada.
     */
    private function deveTentarNovamente(Throwable $excecao): bool
    {
        if ($excecao instanceof ConnectionException) {
            return true;
        }

        if ($excecao instanceof RequestException) {
            $status = $excecao->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }

    private function send(Provider $provider, Service $service, callable $call): array
    {
        $inicio = microtime(true);

        try {
            /** @var Response $resposta */
            $resposta = $call();
        } catch (ConnectionException $e) {
            $this->registrar($provider, $service, 0, (microtime(true) - $inicio) * 1000);

            throw new ProviderUnavailableException($provider, $service, $this->redigirCredenciais($e->getMessage()));
        }

        $this->registrar($provider, $service, $resposta->status(), (microtime(true) - $inicio) * 1000);

        if ($resposta->failed()) {
            throw $this->traduzirErro($provider, $service, $resposta);
        }

        return $resposta->json() ?? [];
    }

    /**
     * Fora de uma operacao, cada chamada vira um evento. Dentro, acumula: quem
     * emite e o finally de operacao().
     */
    private function registrar(Provider $provider, Service $service, int $status, float $duracaoMs): void
    {
        if ($this->operacaoEmCurso !== null) {
            $this->operacaoEmCurso['calls']++;
            $this->operacaoEmCurso['durationMs'] += $duracaoMs;
            $this->operacaoEmCurso['status'] = $status;

            return;
        }

        $this->events->dispatch(new MapRequestCompleted(
            provider: $provider,
            service: $service,
            httpStatus: $status,
            durationMs: round($duracaoMs, 2),
        ));
    }

    private function traduzirErro(Provider $provider, Service $service, Response $resposta): ProviderRequestException
    {
        $status = $resposta->status();
        $corpo = $resposta->json();

        // O computeRouteMatrix do Google embrulha o erro num array
        // ([{"error": {...}}]), diferente de todos os outros endpoints. Sem este
        // fallback a mensagem que explica a falha — "the product of the number of
        // origins and destinations must be <= 625" — se perde, e o consumidor
        // recebe um "Bad Request" sem causa.
        $erro = $corpo['error'] ?? $corpo[0]['error'] ?? null;

        $codigo = $erro['status'] ?? $erro['code'] ?? null;
        $mensagem = $this->redigirCredenciais($erro['message'] ?? $resposta->reason() ?? 'falha na chamada ao provider');

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

    private function redigirCredenciais(string $texto): string
    {
        return CredentialRedaction::redigir($texto);
    }
}
