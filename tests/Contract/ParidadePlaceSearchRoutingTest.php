<?php

namespace BeeDelivery\BeeMaps\Tests\Contract;

use BeeDelivery\BeeMaps\DTOs\Requests\PlaceSearchRequest;
use BeeDelivery\BeeMaps\DTOs\Requests\RouteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\PlaceCollection;
use BeeDelivery\BeeMaps\DTOs\Responses\Route;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

final class ParidadePlaceSearchRoutingTest extends TestCase
{
    private function fakeTudo(): void
    {
        $fixture = fn (string $path) => json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/' . $path),
            true,
        );

        Http::fake([
            'places.googleapis.com/*' => Http::response($fixture('google/place-search.json'), 200),
            'routes.googleapis.com/*' => Http::response($fixture('google/route.json'), 200),
            'discover.search.hereapi.com/*' => Http::response($fixture('here/discover.json'), 200),
            'wps.hereapi.com/*' => Http::response($fixture('here/findsequence.json'), 200),
            'router.hereapi.com/*' => Http::response($fixture('here/route.json'), 200),
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
    public function test_place_search_returns_the_same_contract(Provider $provider): void
    {
        $this->fakeTudo();

        $collection = $this->app->make(MapServiceFactory::class)
            ->placeSearch($provider)
            ->search(new PlaceSearchRequest('farmacia', new Coordinates(-23.5, -46.6)));

        $this->assertInstanceOf(PlaceCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count());

        foreach ($collection as $place) {
            $this->assertNotSame('', $place->name);
            $this->assertNotSame('', $place->address->formatted);
            $this->assertSame($provider, $place->place->provider);
            $this->assertNotSame('', $place->place->id);
        }

        // Os dois lados devolvem endereco ESTRUTURADO, nao so o formatado:
        // e a D16 (field mask Enterprise no Google) virando asserção. Se alguem
        // tirar places.addressComponents do field mask para economizar, este
        // teste quebra em vez de a POC comparar dados assimetricos.
        $first = $collection->first();
        $this->assertSame('Drogaria Sao Paulo', $first->name);
        $this->assertSame('Avenida Paulista', $first->address->street);
        $this->assertSame('1000', $first->address->number);
        $this->assertSame('Bela Vista', $first->address->neighborhood);
        $this->assertSame('Sao Paulo', $first->address->city);
        $this->assertSame('SP', $first->address->state);
        $this->assertSame('Brasil', $first->address->country);
        $this->assertSame('01310100', $first->address->postalCode);
        $this->assertEqualsWithDelta(-23.5615, $first->coordinates->latitude, 0.0001);
    }

    /**
     * As duas fixtures de rota descrevem um percurso de DUAS pernas, embora a
     * requisicao tenha dois intermediarios (o que na vida real daria tres). Isso
     * e proposital: o objetivo aqui e o contrato e os totais, e manter o mesmo
     * numero de pernas nos dois lados deixa a divergencia que importa — a
     * polyline de rota, D17 — isolada de uma diferenca de fixture.
     */
    #[DataProvider('providers')]
    public function test_a_route_with_intermediates_returns_the_same_contract(Provider $provider): void
    {
        $this->fakeTudo();

        $route = $this->app->make(MapServiceFactory::class)
            ->routing($provider)
            ->route(new RouteRequest(
                origin: new Coordinates(-23.5, -46.6),
                destination: new Coordinates(-23.6, -46.7),
                intermediates: [new Coordinates(-23.55, -46.65), new Coordinates(-23.58, -46.68)],
                mode: TravelMode::Drive,
                optimizeIntermediates: true,
                includePolyline: true,
                includeLegs: true,
            ));

        $this->assertInstanceOf(Route::class, $route);

        // Totais convergem porque as duas fixtures descrevem a mesma rota:
        // 5200 + 7200 metros e 780 + 1050 segundos.
        $this->assertSame(12400, $route->distance->meters);
        $this->assertSame(1830, $route->duration->seconds);
        $this->assertSame(12.4, $route->distance->kilometers());

        $this->assertSame([1, 0], $route->optimizedOrder);

        $this->assertCount(2, $route->legs);

        foreach ($route->legs as $leg) {
            $this->assertGreaterThan(0, $leg->distance->meters);
            $this->assertGreaterThan(0, $leg->duration->seconds);
            $this->assertNotNull($leg->polyline);
            // Geometria decodificavel nos dois formatos: e o que garante que
            // Polyline nao virou um wrapper de string opaca.
            $this->assertGreaterThan(1, count($leg->polyline->coordinates()));
        }

        // Divergencia deliberada e documentada (D17): o Google devolve polyline
        // da rota inteira; o HERE, uma por secao — com waypoint intermediario
        // nao existe polyline unica, e concatenar as strings produziria lixo.
        $provider === Provider::Google
            ? $this->assertNotNull($route->polyline)
            : $this->assertNull($route->polyline);
    }

    #[DataProvider('providers')]
    public function test_a_simple_route_returns_the_same_contract(Provider $provider): void
    {
        $this->fakeTudo();

        $route = $this->app->make(MapServiceFactory::class)
            ->routing($provider)
            ->route(new RouteRequest(new Coordinates(-23.5, -46.6), new Coordinates(-23.6, -46.7)));

        $this->assertGreaterThan(0, $route->distance->meters);
        $this->assertGreaterThan(0, $route->duration->seconds);
        // Sem includeLegs, nenhum provider devolve pernas: o contrato e opt-in
        // nos dois lados, nao "o que o provider quiser mandar".
        $this->assertSame([], $route->legs);
        $this->assertSame([], $route->optimizedOrder);
    }
}
