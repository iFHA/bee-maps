<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\GeocodeFilters;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Exceptions\PlaceReferenceProviderMismatchException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class HereGeocodingTest extends TestCase
{
    private function geocoding()
    {
        return $this->app->make(MapServiceFactory::class)->geocoding(Provider::Here);
    }

    public function test_geocode_chama_o_host_de_geocode(): void
    {
        Http::fake([
            'geocode.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/geocode.json'), true),
                200,
            ),
        ]);

        $colecao = $this->geocoding()->geocode('Av Paulista 1000');

        $this->assertCount(1, $colecao);
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'q=Av Paulista 1000'));
    }

    public function test_reverse_chama_o_host_de_revgeocode(): void
    {
        Http::fake([
            'revgeocode.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/geocode.json'), true),
                200,
            ),
        ]);

        $this->geocoding()->reverse(new Coordinates(-23.5615, -46.6562));

        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'at=-23.5615000,-46.6562000'));
    }

    public function test_lookup_chama_o_host_de_lookup_e_devolve_um_resultado(): void
    {
        Http::fake([
            'lookup.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/lookup.json'), true),
                200,
            ),
        ]);

        $resultado = $this->geocoding()->lookup(new PlaceReference(Provider::Here, 'here:pds:place:076sxxxx-abcdef'));

        $this->assertNotNull($resultado);
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'id=here:pds:place:076sxxxx-abcdef'));
    }

    public function test_lookup_recusa_place_id_do_google(): void
    {
        $this->expectException(PlaceReferenceProviderMismatchException::class);

        $this->geocoding()->lookup(new PlaceReference(Provider::Google, 'ChIJ0WGkg4FEzpQRrlsz_whLqZs'));
    }

    public function test_filtro_de_pais_alpha2_chega_como_countryCode_alpha3(): void
    {
        Http::fake([
            'geocode.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/geocode.json'), true),
                200,
            ),
        ]);

        $this->geocoding()->geocode('Av Paulista 1000', new GeocodeFilters(country: 'BR'));

        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'in=countryCode:BRA'));
    }

    public function test_filtro_de_pais_alpha3_tambem_funciona(): void
    {
        Http::fake([
            'geocode.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/geocode.json'), true),
                200,
            ),
        ]);

        $this->geocoding()->geocode('Av Paulista 1000', new GeocodeFilters(country: 'BRA'));

        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'in=countryCode:BRA'));
    }

    public function test_filtro_de_pais_invalido_lanca_excecao(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->geocoding()->geocode('Av Paulista 1000', new GeocodeFilters(country: 'XX'));
    }
}
