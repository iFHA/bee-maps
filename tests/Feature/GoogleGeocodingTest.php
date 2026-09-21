<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\PlaceReferenceProviderMismatchException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class GoogleGeocodingTest extends TestCase
{
    private function fakeOk(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/geocode.json'), true),
                200,
            ),
        ]);
    }

    private function geocoding()
    {
        return $this->app->make(MapServiceFactory::class)->geocoding(Provider::Google);
    }

    public function test_geocode_por_texto_envia_parametro_address(): void
    {
        $this->fakeOk();

        $colecao = $this->geocoding()->geocode('Av Paulista 1000');

        $this->assertCount(1, $colecao);
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'address=Av Paulista 1000'));
    }

    public function test_reverse_envia_parametro_latlng(): void
    {
        $this->fakeOk();

        $this->geocoding()->reverse(new Coordinates(-23.5615, -46.6562));

        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'latlng=-23.5615000,-46.6562000'));
    }

    public function test_lookup_envia_place_id_e_devolve_um_resultado(): void
    {
        $this->fakeOk();

        $resultado = $this->geocoding()->lookup(new PlaceReference(Provider::Google, 'ChIJ0WGkg4FEzpQRrlsz_whLqZs'));

        $this->assertNotNull($resultado);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'place_id=ChIJ0WGkg4FEzpQRrlsz_whLqZs'));
    }

    public function test_lookup_recusa_referencia_de_outro_provider(): void
    {
        $this->fakeOk();

        $this->expectException(PlaceReferenceProviderMismatchException::class);

        $this->geocoding()->lookup(new PlaceReference(Provider::Here, 'here:pds:place:123'));
    }
}
