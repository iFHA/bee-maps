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
        $fixture = fn (string $caminho) => json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/' . $caminho),
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
    public function test_place_search_devolve_o_mesmo_contrato(Provider $provider): void
    {
        $this->fakeTudo();

        $colecao = $this->app->make(MapServiceFactory::class)
            ->placeSearch($provider)
            ->search(new PlaceSearchRequest('farmacia', new Coordinates(-23.5, -46.6)));

        $this->assertInstanceOf(PlaceCollection::class, $colecao);
        $this->assertGreaterThan(0, $colecao->count());

        foreach ($colecao as $lugar) {
            $this->assertNotSame('', $lugar->name);
            $this->assertNotSame('', $lugar->address->formatted);
            $this->assertSame($provider, $lugar->place->provider);
            $this->assertNotSame('', $lugar->place->id);
        }

        // Os dois lados devolvem endereco ESTRUTURADO, nao so o formatado:
        // e a D16 (field mask Enterprise no Google) virando asserção. Se alguem
        // tirar places.addressComponents do field mask para economizar, este
        // teste quebra em vez de a POC comparar dados assimetricos.
        $primeiro = $colecao->first();
        $this->assertSame('Drogaria Sao Paulo', $primeiro->name);
        $this->assertSame('Avenida Paulista', $primeiro->address->street);
        $this->assertSame('1000', $primeiro->address->number);
        $this->assertSame('Bela Vista', $primeiro->address->neighborhood);
        $this->assertSame('Sao Paulo', $primeiro->address->city);
        $this->assertSame('SP', $primeiro->address->state);
        $this->assertSame('Brasil', $primeiro->address->country);
        $this->assertSame('01310100', $primeiro->address->postalCode);
        $this->assertEqualsWithDelta(-23.5615, $primeiro->coordinates->latitude, 0.0001);
    }

    /**
     * As duas fixtures de rota descrevem um percurso de DUAS pernas, embora a
     * requisicao tenha dois intermediarios (o que na vida real daria tres). Isso
     * e proposital: o objetivo aqui e o contrato e os totais, e manter o mesmo
     * numero de pernas nos dois lados deixa a divergencia que importa — a
     * polyline de rota, D17 — isolada de uma diferenca de fixture.
     */
    #[DataProvider('providers')]
    public function test_rota_com_intermediarios_devolve_o_mesmo_contrato(Provider $provider): void
    {
        $this->fakeTudo();

        $rota = $this->app->make(MapServiceFactory::class)
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

        $this->assertInstanceOf(Route::class, $rota);

        // Totais convergem porque as duas fixtures descrevem a mesma rota:
        // 5200 + 7200 metros e 780 + 1050 segundos.
        $this->assertSame(12400, $rota->distance->meters);
        $this->assertSame(1830, $rota->duration->seconds);
        $this->assertSame(12.4, $rota->distance->kilometers());

        $this->assertSame([1, 0], $rota->optimizedOrder);

        $this->assertCount(2, $rota->legs);

        foreach ($rota->legs as $perna) {
            $this->assertGreaterThan(0, $perna->distance->meters);
            $this->assertGreaterThan(0, $perna->duration->seconds);
            $this->assertNotNull($perna->polyline);
            // Geometria decodificavel nos dois formatos: e o que garante que
            // Polyline nao virou um wrapper de string opaca.
            $this->assertGreaterThan(1, count($perna->polyline->coordinates()));
        }

        // Divergencia deliberada e documentada (D17): o Google devolve polyline
        // da rota inteira; o HERE, uma por secao — com waypoint intermediario
        // nao existe polyline unica, e concatenar as strings produziria lixo.
        $provider === Provider::Google
            ? $this->assertNotNull($rota->polyline)
            : $this->assertNull($rota->polyline);
    }

    #[DataProvider('providers')]
    public function test_rota_simples_devolve_o_mesmo_contrato(Provider $provider): void
    {
        $this->fakeTudo();

        $rota = $this->app->make(MapServiceFactory::class)
            ->routing($provider)
            ->route(new RouteRequest(new Coordinates(-23.5, -46.6), new Coordinates(-23.6, -46.7)));

        $this->assertGreaterThan(0, $rota->distance->meters);
        $this->assertGreaterThan(0, $rota->duration->seconds);
        // Sem includeLegs, nenhum provider devolve pernas: o contrato e opt-in
        // nos dois lados, nao "o que o provider quiser mandar".
        $this->assertSame([], $rota->legs);
        $this->assertSame([], $rota->optimizedOrder);
    }
}
