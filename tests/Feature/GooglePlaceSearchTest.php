<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\PlaceSearchRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\ProviderRateLimitException;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class GooglePlaceSearchTest extends TestCase
{
    private function fake(int $status = 200): void
    {
        Http::fake([
            'places.googleapis.com/v1/places:searchText' => Http::response(
                $status === 200
                    ? json_decode(file_get_contents(__DIR__ . '/../Fixtures/google/place-search.json'), true)
                    : ['error' => ['message' => 'quota', 'status' => 'RESOURCE_EXHAUSTED']],
                $status,
            ),
        ]);
    }

    public function test_searches_places_and_returns_a_typed_collection(): void
    {
        $this->fake();

        $collection = $this->app->make(MapServiceFactory::class)
            ->placeSearch(Provider::Google)
            ->search(new PlaceSearchRequest('farmacia'));

        $this->assertCount(2, $collection);
        $this->assertSame('Drogaria Sao Paulo', $collection->first()->name);

        Http::assertSent(function ($request): bool {
            $this->assertSame('chave-google-de-teste', $request->header('X-Goog-Api-Key')[0]);
            $this->assertStringContainsString('places.addressComponents', $request->header('X-Goog-FieldMask')[0]);
            $this->assertSame('farmacia', $request->data()['textQuery']);

            return true;
        });
    }

    public function test_a_429_becomes_a_typed_exception(): void
    {
        $this->fake(429);

        $this->expectException(ProviderRateLimitException::class);

        $this->app->make(MapServiceFactory::class)
            ->placeSearch(Provider::Google)
            ->search(new PlaceSearchRequest('farmacia'));
    }
}
