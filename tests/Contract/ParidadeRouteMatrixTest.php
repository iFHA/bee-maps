<?php

namespace BeeDelivery\BeeMaps\Tests\Contract;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

final class ParidadeRouteMatrixTest extends TestCase
{
    private function fakeTudo(): void
    {
        $fixture = fn (string $caminho) => json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/' . $caminho),
            true,
        );

        Http::fake([
            'routes.googleapis.com/*' => Http::response($fixture('google/route-matrix.json'), 200),
            'matrix.router.hereapi.com/*' => Http::response($fixture('here/matrix.json'), 200),
        ]);
    }

    public static function providers(): array
    {
        return [
            'google' => [Provider::Google],
            'here' => [Provider::Here],
        ];
    }

    #[DataProvider('providers')]
    public function test_matriz_devolve_o_mesmo_contrato(Provider $provider): void
    {
        $this->fakeTudo();

        $matriz = $this->app->make(MapServiceFactory::class)
            ->routeMatrix($provider)
            ->matrix(new RouteMatrixRequest(
                [new Coordinates(-23.5615, -46.6562), new Coordinates(-23.5505, -46.6425)],
                [new Coordinates(-23.5580, -46.6500), new Coordinates(-23.5540, -46.6470)],
            ));

        $this->assertInstanceOf(RouteMatrixEntryCollection::class, $matriz);

        // Matriz 2x2 completa nos dois: par sem rota vira entrada com
        // reachable=false, nunca entrada ausente.
        $this->assertCount(4, $matriz);

        foreach ([0, 1] as $origem) {
            foreach ([0, 1] as $destino) {
                $entrada = $matriz->entry($origem, $destino);

                $this->assertNotNull($entrada, "Faltou a entrada ({$origem},{$destino}).");
                $this->assertSame($origem, $entrada->originIndex);
                $this->assertSame($destino, $entrada->destinationIndex);
                $this->assertIsBool($entrada->reachable);
                $this->assertGreaterThanOrEqual(0, $entrada->distance->meters);
                $this->assertGreaterThanOrEqual(0, $entrada->duration->seconds);
            }
        }

        // As duas fixtures marcam (1,0) como sem rota — o par inalcancavel tem
        // que aparecer igual nos dois providers, e com medidas zeradas.
        $this->assertFalse($matriz->entry(1, 0)->reachable);
        $this->assertSame(0, $matriz->entry(1, 0)->distance->meters);

        $this->assertTrue($matriz->entry(0, 0)->reachable);
        $this->assertGreaterThan(0, $matriz->entry(0, 0)->distance->meters);
    }
}
