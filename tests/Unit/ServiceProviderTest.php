<?php

namespace BeeDelivery\BeeMaps\Tests\Unit;

use BeeDelivery\BeeMaps\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function test_registra_o_config_do_pacote(): void
    {
        $this->assertSame('pt-BR', config('bee-maps.defaults.language'));
        $this->assertSame(10, config('bee-maps.http.timeout'));
    }

    public function test_a_checagem_de_configuracao_cacheada_existe_e_governa_o_merge(): void
    {
        $provider = new \BeeDelivery\BeeMaps\BeeMapsServiceProvider($this->app);

        $metodo = new \ReflectionMethod($provider, 'configuracaoEstaCacheada');
        $metodo->setAccessible(true);

        // Sob o Testbench a configuracao nunca esta cacheada, entao aqui so da
        // para fixar que a checagem EXISTE e devolve false — e, por consequencia,
        // que o merge roda. O ramo verdadeiro so acontece em producao, com
        // `php artisan config:cache`, onde o .env nao e carregado e reavaliar os
        // env() do arquivo preencheria com null as chaves do operador.
        $this->assertFalse($metodo->invoke($provider));

        // E a prova de que o merge de fato rodou nesta aplicacao:
        $this->assertSame(
            'https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix',
            $this->app['config']->get('bee-maps.google.endpoints.route_matrix'),
        );
    }
}
