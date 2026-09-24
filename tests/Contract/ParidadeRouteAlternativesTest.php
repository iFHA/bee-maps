<?php

namespace BeeDelivery\BeeMaps\Tests\Contract;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\Route;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Rotas alternativas precisam significar a MESMA coisa nos dois provedores:
 * mesmo pedido, mesma forma de resposta, mesma excecao quando o valor nao serve.
 *
 * O que diverge de proposito e apenas o parametro upstream: o `computeRoutes` do
 * Google so aceita liga/desliga, enquanto o `/v8/routes` do HERE honra a
 * quantidade.
 */
final class ParidadeRouteAlternativesTest extends TestCase
{
    private function fake(string $provider, string $fixture): void
    {
        $host = $provider === 'google' ? 'routes.googleapis.com/*' : 'router.hereapi.com/*';

        Http::fake([
            $host => Http::response(
                json_decode(file_get_contents(__DIR__.'/../Fixtures/'.$provider.'/'.$fixture), true),
                200,
            ),
        ]);
    }

    private function route(Provider $provider, int $alternativas): Route
    {
        return $this->app->make(MapServiceFactory::class)
            ->routing($provider)
            ->route(new RouteRequest(
                new Coordinates(-23.5, -46.6),
                new Coordinates(-23.6, -46.7),
                alternatives: $alternativas,
            ));
    }

    public static function providers(): array
    {
        return [
            'google' => [Provider::Google, 'google', 'route-alternativas.json'],
            'here' => [Provider::Here, 'here', 'route-alternativas.json'],
        ];
    }

    /**
     */
    #[DataProvider('providers')]
    public function test_alternativas_chegam_no_dto_nos_dois_provedores(
        Provider $provider,
        string $folder,
        string $fixture,
    ): void {
        $this->fake($folder, $fixture);

        $route = $this->route($provider, 3);

        $this->assertNotSame([], $route->alternatives, 'O provedor devolveu alternativas e elas sumiram no mapper.');

        foreach ($route->alternatives as $alternative) {
            $this->assertInstanceOf(Route::class, $alternative);
            $this->assertGreaterThan(0, $alternative->distance->meters, 'Alternativa sem distancia e medida fabricada.');
            $this->assertSame([], $alternative->alternatives, 'Alternativa nao pode aninhar alternativas.');
        }
    }

    /**
     * Simetria antes de generosidade: sem pedido, nenhum provedor devolve
     * alternativas, ainda que a resposta upstream as traga.
     */
    #[DataProvider('providers')]
    public function test_sem_pedido_nenhum_provedor_devolve_alternativas(
        Provider $provider,
        string $folder,
        string $fixture,
    ): void {
        $this->fake($folder, $fixture);

        $this->assertSame([], $this->route($provider, 0)->alternatives);
    }

    /**
     * Caminho de erro tambem e contrato: o teto do HERE (6) vale para os dois,
     * com a mesma excecao, antes de qualquer chamada upstream.
     */
    #[DataProvider('outOfRangeValues')]
    public function test_valor_fora_da_faixa_e_recusado_antes_da_chamada(int $alternativas): void
    {
        Http::fake();

        $this->expectException(InvalidRequestException::class);

        new RouteRequest(
            new Coordinates(-23.5, -46.6),
            new Coordinates(-23.6, -46.7),
            alternatives: $alternativas,
        );
    }

    public static function outOfRangeValues(): array
    {
        return ['negativo' => [-1], 'acima do teto do HERE' => [7]];
    }

    public function test_google_pede_liga_desliga_e_here_pede_a_quantidade(): void
    {
        $this->fake('google', 'route-alternativas.json');
        $this->route(Provider::Google, 3);

        Http::assertSent(function ($request): bool {
            $this->assertTrue($request->data()['computeAlternativeRoutes']);

            return true;
        });

        $this->fake('here', 'route-alternativas.json');
        $this->route(Provider::Here, 3);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'hereapi')) {
                return true;
            }

            $this->assertStringContainsString('alternatives=3', $request->url());

            return true;
        });
    }

    /**
     * O HERE devolve uma alternativa sem secoes na fixture: resposta incompleta
     * do provider e descarte, nao rota de zero metro.
     */
    public function test_alternativa_sem_medida_nao_vira_rota_de_zero(): void
    {
        $this->fake('here', 'route-alternativas.json');

        foreach ($this->route(Provider::Here, 3)->alternatives as $alternative) {
            $this->assertGreaterThan(0, $alternative->distance->meters);
        }
    }
}
