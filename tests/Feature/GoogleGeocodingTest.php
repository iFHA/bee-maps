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

    public function test_geocode_by_text_sends_the_address_parameter(): void
    {
        $this->fakeOk();

        $collection = $this->geocoding()->geocode('Av Paulista 1000');

        $this->assertCount(1, $collection);
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'address=Av Paulista 1000'));
    }

    public function test_reverse_sends_the_latlng_parameter(): void
    {
        $this->fakeOk();

        $this->geocoding()->reverse(new Coordinates(-23.5615, -46.6562));

        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'latlng=-23.5615000,-46.6562000'));
    }

    public function test_lookup_sends_the_place_id_and_returns_a_result(): void
    {
        $this->fakeOk();

        $result = $this->geocoding()->lookup(new PlaceReference(Provider::Google, 'ChIJ0WGkg4FEzpQRrlsz_whLqZs'));

        $this->assertNotNull($result);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'place_id=ChIJ0WGkg4FEzpQRrlsz_whLqZs'));
    }

    public function test_lookup_refuses_a_reference_from_another_provider(): void
    {
        $this->fakeOk();

        $this->expectException(PlaceReferenceProviderMismatchException::class);

        $this->geocoding()->lookup(new PlaceReference(Provider::Here, 'here:pds:place:123'));
    }

    public function test_an_alpha2_country_filter_arrives_as_alpha2_country(): void
    {
        $this->fakeOk();

        $this->geocoding()->geocode('Av Paulista 1000', new GeocodeFilters(country: 'BR'));

        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'components=country:BR'));
    }

    public function test_an_alpha3_country_filter_is_converted_to_alpha2(): void
    {
        $this->fakeOk();

        $this->geocoding()->geocode('Av Paulista 1000', new GeocodeFilters(country: 'BRA'));

        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'components=country:BR'));
    }

    public function test_an_invalid_country_filter_throws(): void
    {
        $this->fakeOk();

        $this->expectException(InvalidRequestException::class);

        $this->geocoding()->geocode('Av Paulista 1000', new GeocodeFilters(country: 'XX'));
    }
}
