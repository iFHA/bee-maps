<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteRequestMapper;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class GoogleAutocompleteTest extends TestCase
{
    public function test_queries_google_and_returns_typed_suggestions(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/autocomplete.json'), true),
                200,
            ),
        ]);

        $collection = $this->app->make(MapServiceFactory::class)
            ->autocomplete(Provider::Google)
            ->suggest(new AutocompleteRequest('Av Paulista'));

        $this->assertCount(2, $collection);
        $this->assertSame('Avenida Paulista, 1000', $collection->first()->mainText);

        Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-Api-Key', 'chave-google-de-teste')
            && $request->hasHeader('X-Goog-FieldMask', (new GoogleAutocompleteRequestMapper())->fieldMask()));
    }

    public function test_empty_countries_uses_the_configured_region_as_the_default(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/autocomplete.json'), true),
                200,
            ),
        ]);

        $this->app->make(MapServiceFactory::class)
            ->autocomplete(Provider::Google)
            ->suggest(new AutocompleteRequest('Av Paulista'));

        Http::assertSent(fn ($request) => $request['includedRegionCodes'] === ['BR']);
    }
}
