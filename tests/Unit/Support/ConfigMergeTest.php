<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\Support\ConfigMerge;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class ConfigMergeTest extends TestCase
{
    public function test_a_new_nested_key_shows_up_in_an_old_published_config(): void
    {
        $patterns = ['google' => [
            'key' => null,
            'matrix_max_elements' => 625,
            'endpoints' => ['routing' => 'r', 'route_matrix' => 'rm'],
        ]];

        $published = ['google' => [
            'key' => 'chave-do-consumidor',
            'endpoints' => ['routing' => 'r'],
        ]];

        $result = ConfigMerge::deep($patterns, $published);

        // O que o consumidor definiu continua valendo...
        $this->assertSame('chave-do-consumidor', $result['google']['key']);
        // ...e o que e novo no pacote aparece, em vez de sumir.
        $this->assertSame('rm', $result['google']['endpoints']['route_matrix']);
        $this->assertSame(625, $result['google']['matrix_max_elements']);
    }

    public function test_a_published_list_wins_whole(): void
    {
        $patterns = ['providers' => ['GoogleProvider', 'HereProvider']];
        $published = ['providers' => ['GoogleProvider']];

        $result = ConfigMerge::deep($patterns, $published);

        // Sem esta regra, um consumidor que desligou o HERE o veria voltar.
        $this->assertSame(['GoogleProvider'], $result['providers']);
    }

    public function test_without_a_published_config_it_returns_the_defaults(): void
    {
        $patterns = ['a' => 1, 'b' => ['c' => 2]];

        $this->assertSame($patterns, ConfigMerge::deep($patterns, []));
    }

    public function test_a_published_scalar_beats_the_default(): void
    {
        $result = ConfigMerge::deep(['http' => ['timeout' => 10]], ['http' => ['timeout' => 30]]);

        $this->assertSame(30, $result['http']['timeout']);
    }

    public function test_a_published_null_is_respected_and_does_not_fall_back_to_the_default(): void
    {
        // Consumidor que zerou uma chave de proposito nao pode ve-la ressuscitar.
        $result = ConfigMerge::deep(['here' => ['api_key' => 'padrao']], ['here' => ['api_key' => null]]);

        $this->assertNull($result['here']['api_key']);
    }

    public function test_a_published_list_with_a_hole_still_wins_whole(): void
    {
        $patterns = ['providers' => ['GoogleProvider', 'HereProvider']];

        // Consumidor removeu o indice 0 (unset ou array_filter sem array_values):
        // sobra [1 => Here], que NAO e lista. Sem normalizar, o merge caia no
        // laco por chave e o GoogleProvider ressuscitava no indice 0 — a mesma
        // armadilha de array_filter que o RouteMatrixRequest ja documenta.
        $published = ['providers' => [1 => 'HereProvider']];

        $result = ConfigMerge::deep($patterns, $published);

        $this->assertSame(['HereProvider'], $result['providers']);
        $this->assertNotContains('GoogleProvider', $result['providers']);
    }

    public function test_an_empty_published_list_is_respected(): void
    {
        $result = ConfigMerge::deep(['providers' => ['GoogleProvider']], ['providers' => []]);

        $this->assertSame([], $result['providers']);
    }

    public function test_an_empty_array_default_does_not_discard_published_keys(): void
    {
        // array_is_list([]) e true, entao um padrao vazio caia na regra de lista
        // e rodava array_values() sobre o mapa publicado, perdendo as chaves.
        $result = ConfigMerge::deep(
            ['http' => ['headers' => []]],
            ['http' => ['headers' => ['X-Trace' => 'abc']]],
        );

        $this->assertSame(['X-Trace' => 'abc'], $result['http']['headers']);
    }

    public function test_an_empty_array_default_with_a_published_list_is_preserved_too(): void
    {
        $result = ConfigMerge::deep(['tags' => []], ['tags' => ['a', 'b']]);

        $this->assertSame(['a', 'b'], $result['tags']);
    }
}
