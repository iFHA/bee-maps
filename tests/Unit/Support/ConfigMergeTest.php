<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\Support\ConfigMerge;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class ConfigMergeTest extends TestCase
{
    public function test_chave_aninhada_nova_aparece_em_config_publicado_antigo(): void
    {
        $padroes = ['google' => [
            'key' => null,
            'matrix_max_elements' => 625,
            'endpoints' => ['routing' => 'r', 'route_matrix' => 'rm'],
        ]];

        $publicado = ['google' => [
            'key' => 'chave-do-consumidor',
            'endpoints' => ['routing' => 'r'],
        ]];

        $resultado = ConfigMerge::deep($padroes, $publicado);

        // O que o consumidor definiu continua valendo...
        $this->assertSame('chave-do-consumidor', $resultado['google']['key']);
        // ...e o que e novo no pacote aparece, em vez de sumir.
        $this->assertSame('rm', $resultado['google']['endpoints']['route_matrix']);
        $this->assertSame(625, $resultado['google']['matrix_max_elements']);
    }

    public function test_lista_publicada_vence_inteira(): void
    {
        $padroes = ['providers' => ['GoogleProvider', 'HereProvider']];
        $publicado = ['providers' => ['GoogleProvider']];

        $resultado = ConfigMerge::deep($padroes, $publicado);

        // Sem esta regra, um consumidor que desligou o HERE o veria voltar.
        $this->assertSame(['GoogleProvider'], $resultado['providers']);
    }

    public function test_sem_config_publicado_devolve_os_padroes(): void
    {
        $padroes = ['a' => 1, 'b' => ['c' => 2]];

        $this->assertSame($padroes, ConfigMerge::deep($padroes, []));
    }

    public function test_escalar_publicado_vence_o_padrao(): void
    {
        $resultado = ConfigMerge::deep(['http' => ['timeout' => 10]], ['http' => ['timeout' => 30]]);

        $this->assertSame(30, $resultado['http']['timeout']);
    }

    public function test_null_publicado_e_respeitado_e_nao_volta_para_o_padrao(): void
    {
        // Consumidor que zerou uma chave de proposito nao pode ve-la ressuscitar.
        $resultado = ConfigMerge::deep(['here' => ['api_key' => 'padrao']], ['here' => ['api_key' => null]]);

        $this->assertNull($resultado['here']['api_key']);
    }

    public function test_lista_publicada_com_buraco_ainda_vence_inteira(): void
    {
        $padroes = ['providers' => ['GoogleProvider', 'HereProvider']];

        // Consumidor removeu o indice 0 (unset ou array_filter sem array_values):
        // sobra [1 => Here], que NAO e lista. Sem normalizar, o merge caia no
        // laco por chave e o GoogleProvider ressuscitava no indice 0 — a mesma
        // armadilha de array_filter que o RouteMatrixRequest ja documenta.
        $publicado = ['providers' => [1 => 'HereProvider']];

        $resultado = ConfigMerge::deep($padroes, $publicado);

        $this->assertSame(['HereProvider'], $resultado['providers']);
        $this->assertNotContains('GoogleProvider', $resultado['providers']);
    }

    public function test_lista_publicada_vazia_e_respeitada(): void
    {
        $resultado = ConfigMerge::deep(['providers' => ['GoogleProvider']], ['providers' => []]);

        $this->assertSame([], $resultado['providers']);
    }

    public function test_padrao_de_array_vazio_nao_descarta_chaves_do_publicado(): void
    {
        // array_is_list([]) e true, entao um padrao vazio caia na regra de lista
        // e rodava array_values() sobre o mapa publicado, perdendo as chaves.
        $resultado = ConfigMerge::deep(
            ['http' => ['headers' => []]],
            ['http' => ['headers' => ['X-Trace' => 'abc']]],
        );

        $this->assertSame(['X-Trace' => 'abc'], $resultado['http']['headers']);
    }

    public function test_padrao_de_array_vazio_com_lista_publicada_tambem_e_preservado(): void
    {
        $resultado = ConfigMerge::deep(['tags' => []], ['tags' => ['a', 'b']]);

        $this->assertSame(['a', 'b'], $resultado['tags']);
    }
}
