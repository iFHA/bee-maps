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
        $fixture = fn (string $path) => json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/' . $path),
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
    public function test_the_matrix_returns_the_same_contract(Provider $provider): void
    {
        $this->fakeTudo();

        $matrix = $this->app->make(MapServiceFactory::class)
            ->routeMatrix($provider)
            ->matrix(new RouteMatrixRequest(
                [new Coordinates(-23.5615, -46.6562), new Coordinates(-23.4400, -46.5300)],
                [
                    new Coordinates(-23.5580, -46.6500),
                    new Coordinates(-23.5540, -46.6470),
                    new Coordinates(-23.5505, -46.6425),
                ],
            ));

        $this->assertInstanceOf(RouteMatrixEntryCollection::class, $matrix);

        // 2x3, e nao 2x2: numa matriz quadrada a diagonal nao muda ao transpor,
        // entao a transposicao so era pega por causa de um errorCode assimetrico.
        $this->assertCount(6, $matrix);

        foreach ([0, 1] as $origin) {
            foreach ([0, 1, 2] as $destination) {
                $entry = $matrix->entry($origin, $destination);

                $this->assertNotNull($entry, "Faltou a entrada ({$origin},{$destination}).");
                $this->assertSame($origin, $entry->originIndex);
                $this->assertSame($destination, $entry->destinationIndex);
                $this->assertIsBool($entry->reachable);
                $this->assertGreaterThanOrEqual(0, $entry->distance->meters);
                $this->assertGreaterThanOrEqual(0, $entry->duration->seconds);
            }
        }

        // A origem 0 esta no centro e a 1 a ~25 km: toda linha da origem 1 tem
        // que ser uma ordem de grandeza maior. E esta asserção que torna a
        // transposicao impossivel de passar despercebida.
        foreach ([1, 2] as $destination) {
            $this->assertLessThan(5000, $matrix->entry(0, $destination)->distance->meters);
            $this->assertGreaterThan(20000, $matrix->entry(1, $destination)->distance->meters);
        }

        // As duas fixtures marcam (1,0) como sem rota — o par inalcancavel tem
        // que aparecer igual nos dois providers, e com medidas zeradas.
        $this->assertFalse($matrix->entry(1, 0)->reachable);
        $this->assertSame(0, $matrix->entry(1, 0)->distance->meters);

        $this->assertTrue($matrix->entry(0, 0)->reachable);
        $this->assertSame(1225, $matrix->entry(0, 0)->distance->meters);
    }
}
