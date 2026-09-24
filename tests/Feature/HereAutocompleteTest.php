<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\ConfigurationException;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class HereAutocompleteTest extends TestCase
{
    private function fakeAutosuggest(): void
    {
        Http::fake([
            'autosuggest.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/autosuggest.json'), true),
                200,
            ),
        ]);
    }

    private function fakeAutocomplete(): void
    {
        Http::fake([
            'autocomplete.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/autocomplete.json'), true),
                200,
            ),
        ]);
    }

    private function estrategia(string $valor): void
    {
        $this->app['config']->set('bee-maps.here.autocomplete_strategy', $valor);
    }

    private function suggest(AutocompleteRequest $request)
    {
        return $this->app->make(MapServiceFactory::class)
            ->autocomplete(Provider::Here)
            ->suggest($request);
    }

    public function test_com_coordenada_o_padrao_vai_para_o_autosuggest(): void
    {
        // Sem fake do /autocomplete: se o despacho errar o endpoint,
        // preventStrayRequests quebra o teste em vez de mascarar a rota.
        $this->fakeAutosuggest();

        $colecao = $this->suggest(new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6)));

        $this->assertCount(4, $colecao);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'apiKey=chave-here-de-teste'));
    }

    public function test_sem_coordenada_o_padrao_vai_para_o_autocomplete_e_cobre_o_pais(): void
    {
        $this->fakeAutocomplete();

        $colecao = $this->suggest(new AutocompleteRequest('Rua Blumenau'));

        $this->assertCount(3, $colecao);

        Http::assertSent(function ($request): bool {
            $url = urldecode($request->url());

            // O caso "cadastro de empresa": sem ponto de referencia, a busca
            // tem que valer para o Brasil inteiro. O /autosuggest responderia
            // 400 aqui, porque nele `in=countryCode` precisa vir acompanhado
            // de `at`/`in=circle`/`in=bbox`.
            $this->assertStringContainsString('in=countryCode:BRA', $url);
            $this->assertStringNotContainsString('at=', $url);
            $this->assertStringNotContainsString('circle:', $url);

            return true;
        });
    }

    public function test_centro_configurado_nao_encolhe_a_busca_nacional_no_modo_auto(): void
    {
        // O autosuggest_center segue setado pelo defineEnvironment. Se ele
        // influenciasse o modo `auto`, esta chamada viraria um circle de 50 km
        // em volta de Sao Paulo e "Rua Blumenau" (Joinville) sumiria.
        $this->assertNotNull($this->app['config']->get('bee-maps.here.autosuggest_center'));

        $this->fakeAutocomplete();

        $this->suggest(new AutocompleteRequest('Rua Blumenau'));

        Http::assertSent(fn ($request) => ! str_contains(urldecode($request->url()), 'circle:'));
    }

    public function test_near_e_radius_geram_circle_e_countryCode_como_parametros_in_repetidos(): void
    {
        $this->fakeAutosuggest();

        $this->suggest(new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), 3000, ['BR']));

        Http::assertSent(function ($request): bool {
            $url = urldecode($request->url());

            $this->assertStringNotContainsString('in[0]=', $url);
            $this->assertStringContainsString('in=circle:-23.5000000,-46.6000000;r=3000', $url);
            $this->assertStringContainsString('in=countryCode:BRA', $url);
            // 400 "Mutually exclusive parameters violated" se `at` vier junto.
            $this->assertStringNotContainsString('at=', $url);

            return true;
        });
    }

    public function test_estrategia_autocomplete_forcada_usa_o_endpoint_mesmo_com_coordenada(): void
    {
        $this->estrategia('autocomplete');
        $this->fakeAutocomplete();

        $this->suggest(new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), 3000, ['BR']));

        Http::assertSent(function ($request): bool {
            $url = urldecode($request->url());

            $this->assertStringContainsString('autocomplete.search.hereapi.com', $url);
            $this->assertStringContainsString('in=circle:-23.5000000,-46.6000000;r=3000', $url);

            return true;
        });
    }

    public function test_estrategia_autosuggest_forcada_cai_no_centro_configurado_sem_coordenada(): void
    {
        $this->estrategia('autosuggest');
        $this->fakeAutosuggest();

        $this->suggest(new AutocompleteRequest('Av Paulista'));

        Http::assertSent(
            fn ($request) => str_contains(urldecode($request->url()), 'circle:-23.5615000,-46.6562000;r=50000'),
        );
    }

    public function test_estrategia_autosuggest_sem_centro_e_sem_coordenada_falha_antes_da_rede(): void
    {
        // Sem Http::fake: se a excecao nao vier, preventStrayRequests quebra o
        // teste — que e o comportamento desejado.
        $this->estrategia('autosuggest');
        $this->app['config']->set('bee-maps.here.autosuggest_center', null);

        $this->expectException(InvalidRequestException::class);

        $this->suggest(new AutocompleteRequest('Av Paulista'));
    }

    public function test_estrategia_invalida_e_erro_de_configuracao(): void
    {
        $this->estrategia('discover');

        $this->expectException(ConfigurationException::class);

        $this->suggest(new AutocompleteRequest('Av Paulista'));
    }

    public function test_countries_vazio_usa_a_regiao_configurada_como_padrao(): void
    {
        $this->fakeAutocomplete();

        $this->suggest(new AutocompleteRequest('Av Paulista'));

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'in=countryCode:BRA'));
    }
}
