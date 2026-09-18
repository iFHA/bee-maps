<?php

namespace BeeDelivery\BeeMaps\Tests\Contract;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResultCollection;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Garante que os dois providers devolvem o MESMO contrato para a mesma entrada.
 * E este teste que transforma "paridade" de intencao em asserção que quebra o CI.
 */
final class ParidadeAutocompleteGeocodingTest extends TestCase
{
    private function fakeTudo(): void
    {
        $fixture = fn (string $caminho) => json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/' . $caminho),
            true,
        );

        Http::fake([
            'places.googleapis.com/*' => Http::response($fixture('google/autocomplete.json'), 200),
            'maps.googleapis.com/*' => Http::response($fixture('google/geocode.json'), 200),
            'autosuggest.search.hereapi.com/*' => Http::response($fixture('here/autosuggest.json'), 200),
            'geocode.search.hereapi.com/*' => Http::response($fixture('here/geocode.json'), 200),
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
    public function test_autocomplete_devolve_o_mesmo_contrato(Provider $provider): void
    {
        $this->fakeTudo();

        $colecao = $this->app->make(MapServiceFactory::class)
            ->autocomplete($provider)
            ->suggest(new AutocompleteRequest('Av Paulista'));

        $this->assertInstanceOf(SuggestionCollection::class, $colecao);
        $this->assertGreaterThan(0, $colecao->count());

        foreach ($colecao as $sugestao) {
            $this->assertNotSame('', $sugestao->description);
            $this->assertNotSame('', $sugestao->mainText);
            $this->assertIsBool($sugestao->isEstablishment);

            if ($sugestao->place !== null) {
                $this->assertSame($provider, $sugestao->place->provider);
                $this->assertNotSame('', $sugestao->place->id);
            }
        }
    }

    #[DataProvider('providers')]
    public function test_geocode_devolve_o_mesmo_contrato(Provider $provider): void
    {
        $this->fakeTudo();

        $colecao = $this->app->make(MapServiceFactory::class)
            ->geocoding($provider)
            ->geocode('Av Paulista 1000');

        $this->assertInstanceOf(GeocodeResultCollection::class, $colecao);
        $this->assertGreaterThan(0, $colecao->count());

        $resultado = $colecao->first();

        $this->assertSame('Avenida Paulista', $resultado->address->street);
        $this->assertSame('1000', $resultado->address->number);
        $this->assertSame('Sao Paulo', $resultado->address->city);
        $this->assertSame('SP', $resultado->address->state);
        $this->assertSame('01310100', $resultado->address->postalCode);
        $this->assertNotSame('', $resultado->address->formatted);
        $this->assertEqualsWithDelta(-23.5615, $resultado->coordinates->latitude, 0.0001);
        $this->assertIsBool($resultado->partial);
        $this->assertSame($provider, $resultado->place->provider);

        // Divergencia conhecida e deliberada: matchScore existe so onde o provider
        // reporta grau de casamento. Ver a nota da Task 11.
        $provider === Provider::Google
            ? $this->assertNull($resultado->matchScore)
            : $this->assertIsFloat($resultado->matchScore);
    }
}
