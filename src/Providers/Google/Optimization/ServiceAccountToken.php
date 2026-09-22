<?php

namespace BeeDelivery\BeeMaps\Providers\Google\Optimization;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\Service;
use BeeDelivery\BeeMaps\Exceptions\ConfigurationException;
use BeeDelivery\BeeMaps\Exceptions\MissingCredentialsException;
use BeeDelivery\BeeMaps\Exceptions\ProviderAuthenticationException;
use Google\Client;

/**
 * OAuth de service account para a Cloud Fleet Routing — o unico endpoint do
 * pacote que nao aceita chave de API.
 *
 * O google/apiclient entra no composer.json como `suggest`, nao como `require`:
 * arrasta google/auth, firebase/php-jwt e Guzzle para todo mundo que instalar o
 * pacote, por um caminho que a estrategia default (matrix_tsp) nao usa.
 */
final class ServiceAccountToken implements AccessToken
{
    /**
     * @param array<string, mixed> $credenciais Conteudo do JSON de service account.
     */
    public function __construct(
        private readonly array $credenciais,
        private readonly string $escopo,
    ) {
        if (! class_exists(Client::class)) {
            throw new ConfigurationException(
                'A estrategia fleet_routing precisa do pacote google/apiclient, que o '
                . 'bee-maps apenas sugere: rode `composer require google/apiclient`. '
                . 'Se nao quiser a dependencia, deixe bee-maps.google.route_optimization'
                . '.min_distance_api em "matrix_tsp", que e o default e nao precisa dela.',
            );
        }

        foreach (['project_id', 'private_key', 'client_email'] as $obrigatorio) {
            if (empty($this->credenciais[$obrigatorio])) {
                throw MissingCredentialsException::make(
                    Provider::Google,
                    'bee-maps.google.route_optimization.service_account.' . $obrigatorio,
                );
            }
        }
    }

    public function valor(): string
    {
        $client = new Client();
        $client->setAuthConfig($this->credenciais);
        $client->addScope($this->escopo);

        $token = $client->fetchAccessTokenWithAssertion()['access_token'] ?? null;

        // Sem isto, um token vazio viraria header "Bearer " e o erro voltaria do
        // Google como 401 generico, sem indicar que a causa e a credencial local.
        return is_string($token) && $token !== ''
            ? $token
            : throw new ProviderAuthenticationException(
                Provider::Google,
                Service::RouteOptimization,
                'A service account nao devolveu access_token para a Cloud Fleet Routing.',
                401,
            );
    }
}
