<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\PlaceSearchRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class HerePlaceSearchTest extends TestCase
{
    private function fake(): void
    {
        Http::fake([
            'discover.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/discover.json'), true),
                200,
            ),
        ]);
    }

    public function test_busca_lugares_e_devolve_colecao_tipada(): void
    {
        $this->fake();

        $colecao = $this->app->make(MapServiceFactory::class)
            ->placeSearch(Provider::Here)
            ->search(new PlaceSearchRequest('farmacia', new Coordinates(-23.5, -46.6)));

        $this->assertCount(2, $colecao);
        $this->assertSame('Drogaria Sao Paulo', $colecao->first()->name);

        Http::assertSent(function ($request): bool {
            $url = urldecode($request->url());

            $this->assertStringContainsString('at=-23.5000000,-46.6000000', $url);
            $this->assertStringContainsString('apiKey=chave-here-de-teste', $url);

            return true;
        });
    }

    public function test_sem_contexto_geografico_falha_antes_de_sair_para_a_rede(): void
    {
        // Sem Http::fake: se a excecao nao for lancada, preventStrayRequests
        // quebra o teste — que e exatamente o comportamento desejado.
        $this->app['config']->set('bee-maps.defaults.region', '');

        $this->expectException(InvalidRequestException::class);

        $this->app->make(MapServiceFactory::class)
            ->placeSearch(Provider::Here)
            ->search(new PlaceSearchRequest('farmacia'));
    }
}
