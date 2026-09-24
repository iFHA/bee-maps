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
    private function points(int $howMany): array
    {
        $points = [];

        for ($i = 0; $i < $howMany; $i++) {
            $points[] = new Coordinates(-23.5 - ($i / 10000), -46.6 - ($i / 10000));
        }

        return $points;
    }

    public function test_computes_the_matrix_and_returns_an_addressable_collection(): void
    {
        Http::fake(['routes.googleapis.com/distanceMatrix/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/route-matrix.json'), true),
            200,
        )]);

        $matrix = $this->app->make(MapServiceFactory::class)
            ->routeMatrix(Provider::Google)
            ->matrix(new RouteMatrixRequest($this->points(2), $this->points(3)));

        $this->assertCount(6, $matrix);
        $this->assertSame(1225, $matrix->entry(0, 0)->distance->meters);
        $this->assertFalse($matrix->entry(1, 0)->reachable);

        Http::assertSent(function ($request): bool {
            $this->assertSame('chave-google-de-teste', $request->header('X-Goog-Api-Key')[0]);
            $this->assertSame('originIndex,destinationIndex,distanceMeters,duration,condition', $request->header('X-Goog-FieldMask')[0]);

            return true;
        });
    }

    public function test_a_matrix_above_the_limit_fails_before_going_to_the_network(): void
    {
        // Sem Http::fake: se a chamada sair, preventStrayRequests quebra o teste.
        // 26 x 26 = 676 elementos, acima dos 625 que a API aceita.
        $this->expectException(MatrixTooLargeException::class);
        $this->expectExceptionMessageMatches('/676.*625|625.*676/s');

        $this->app->make(MapServiceFactory::class)
            ->routeMatrix(Provider::Google)
            ->matrix(new RouteMatrixRequest($this->points(26), $this->points(26)));
    }

    public function test_an_empty_limit_in_the_config_means_no_guard(): void
    {
        // `BEE_MAPS_GOOGLE_MATRIX_MAX_ELEMENTS=` no .env faz env() devolver ''
        // (o default so vale quando a variavel nao existe), e (int) '' e 0 — o
        // que transformava a guarda em "recuse toda requisicao".
        $this->app['config']->set('bee-maps.google.matrix_max_elements', '');

        Http::fake(['routes.googleapis.com/distanceMatrix/*' => Http::response(
            json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/route-matrix.json'), true),
            200,
        )]);

        $matrix = $this->app->make(MapServiceFactory::class)
            ->routeMatrix(Provider::Google)
            ->matrix(new RouteMatrixRequest($this->points(2), $this->points(3)));

        $this->assertCount(6, $matrix);
    }
}
