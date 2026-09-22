<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class HereRouteMatrixTest extends TestCase
{
    public function test_calcula_matriz_e_devolve_colecao_endereçavel(): void
    {
        Http::fake(['matrix.router.hereapi.com/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/matrix.json'), true),
            200,
        )]);

        $matriz = $this->app->make(MapServiceFactory::class)
            ->routeMatrix(Provider::Here)
            ->matrix(new RouteMatrixRequest(
                [new Coordinates(-23.5615, -46.6562), new Coordinates(-23.5505, -46.6425)],
                [new Coordinates(-23.5580, -46.6500), new Coordinates(-23.5540, -46.6470)],
            ));

        $this->assertCount(4, $matriz);
        $this->assertSame(1225, $matriz->entry(0, 0)->distance->meters);
        $this->assertFalse($matriz->entry(1, 0)->reachable);

        Http::assertSent(function ($request): bool {
            $url = urldecode($request->url());

            // D10: so o modo sincrono na Fase 1.
            $this->assertStringContainsString('async=false', $url);
            $this->assertStringContainsString('apiKey=chave-here-de-teste', $url);
            $this->assertSame(['type' => 'autoCircle'], $request->data()['regionDefinition']);

            return true;
        });
    }

    public function test_limite_vazio_no_config_significa_sem_guarda(): void
    {
        $this->app['config']->set('bee-maps.here.matrix_max_elements', '');

        Http::fake(['matrix.router.hereapi.com/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/matrix.json'), true),
            200,
        )]);

        $matriz = $this->app->make(MapServiceFactory::class)
            ->routeMatrix(Provider::Here)
            ->matrix(new RouteMatrixRequest(
                [new Coordinates(-23.5615, -46.6562), new Coordinates(-23.5505, -46.6425)],
                [new Coordinates(-23.5580, -46.6500), new Coordinates(-23.5540, -46.6470)],
            ));

        $this->assertCount(4, $matriz);
    }
}
