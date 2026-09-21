<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class HereAutocompleteTest extends TestCase
{
    public function test_consulta_o_here_e_devolve_sugestoes_tipadas(): void
    {
        Http::fake([
            'autosuggest.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/autosuggest.json'), true),
                200,
            ),
        ]);

        $colecao = $this->app->make(MapServiceFactory::class)
            ->autocomplete(Provider::Here)
            ->suggest(new AutocompleteRequest('Av Paulista'));

        $this->assertCount(3, $colecao);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'apiKey=chave-here-de-teste'));
    }

    public function test_countries_vazio_usa_a_regiao_configurada_como_padrao(): void
    {
        Http::fake([
            'autosuggest.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/autosuggest.json'), true),
                200,
            ),
        ]);

        $this->app->make(MapServiceFactory::class)
            ->autocomplete(Provider::Here)
            ->suggest(new AutocompleteRequest('Av Paulista'));

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'in=countryCode:BRA'));
    }

    public function test_near_e_radius_geram_circle_e_countryCode_como_parametros_in_repetidos(): void
    {
        Http::fake([
            'autosuggest.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/autosuggest.json'), true),
                200,
            ),
        ]);

        $this->app->make(MapServiceFactory::class)
            ->autocomplete(Provider::Here)
            ->suggest(new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), 3000, ['BR']));

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

    public function test_sem_centro_configurado_e_sem_coordenada_falha_antes_da_rede(): void
    {
        // Sem Http::fake: se a excecao nao vier, preventStrayRequests quebra o
        // teste — que e o comportamento desejado.
        $this->app['config']->set('bee-maps.here.autosuggest_center', null);

        $this->expectException(InvalidRequestException::class);

        $this->app->make(MapServiceFactory::class)
            ->autocomplete(Provider::Here)
            ->suggest(new AutocompleteRequest('Av Paulista'));
    }
}
