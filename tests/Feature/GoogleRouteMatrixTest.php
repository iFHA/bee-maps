<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\MatrixTooLargeException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class GoogleRouteMatrixTest extends TestCase
{
    private function pontos(int $quantidade): array
    {
        $pontos = [];

        for ($i = 0; $i < $quantidade; $i++) {
            $pontos[] = new Coordinates(-23.5 - ($i / 10000), -46.6 - ($i / 10000));
        }

        return $pontos;
    }

    public function test_calcula_matriz_e_devolve_colecao_endereçavel(): void
    {
        Http::fake(['routes.googleapis.com/distanceMatrix/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/route-matrix.json'), true),
            200,
        )]);

        $matriz = $this->app->make(MapServiceFactory::class)
            ->routeMatrix(Provider::Google)
            ->matrix(new RouteMatrixRequest($this->pontos(2), $this->pontos(3)));

        $this->assertCount(6, $matriz);
        $this->assertSame(1225, $matriz->entry(0, 0)->distance->meters);
        $this->assertFalse($matriz->entry(1, 0)->reachable);

        Http::assertSent(function ($request): bool {
            $this->assertSame('chave-google-de-teste', $request->header('X-Goog-Api-Key')[0]);
            $this->assertSame('originIndex,destinationIndex,distanceMeters,duration,condition', $request->header('X-Goog-FieldMask')[0]);

            return true;
        });
    }

    public function test_matriz_acima_do_limite_falha_antes_de_sair_para_a_rede(): void
    {
        // Sem Http::fake: se a chamada sair, preventStrayRequests quebra o teste.
        // 26 x 26 = 676 elementos, acima dos 625 que a API aceita.
        $this->expectException(MatrixTooLargeException::class);
        $this->expectExceptionMessageMatches('/676.*625|625.*676/s');

        $this->app->make(MapServiceFactory::class)
            ->routeMatrix(Provider::Google)
            ->matrix(new RouteMatrixRequest($this->pontos(26), $this->pontos(26)));
    }

    public function test_limite_vazio_no_config_significa_sem_guarda(): void
    {
        // `BEE_MAPS_GOOGLE_MATRIX_MAX_ELEMENTS=` no .env faz env() devolver ''
        // (o default so vale quando a variavel nao existe), e (int) '' e 0 — o
        // que transformava a guarda em "recuse toda requisicao".
        $this->app['config']->set('bee-maps.google.matrix_max_elements', '');

        Http::fake(['routes.googleapis.com/distanceMatrix/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/route-matrix.json'), true),
            200,
        )]);

        $matriz = $this->app->make(MapServiceFactory::class)
            ->routeMatrix(Provider::Google)
            ->matrix(new RouteMatrixRequest($this->pontos(2), $this->pontos(3)));

        $this->assertCount(6, $matriz);
    }
}
