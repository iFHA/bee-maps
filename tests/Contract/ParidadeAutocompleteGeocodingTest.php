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

        $primeira = $colecao->first();

        // mainText converge para o mesmo valor nos dois providers: e o campo
        // estruturado (titulo do HERE / structuredFormat.mainText do Google).
        // description e secondaryText legitimamente diferem em pontuacao e
        // conteudo entre providers, por isso nao sao comparados entre si aqui.
        $this->assertSame('Avenida Paulista, 1000', $primeira->mainText);

        // mainText e description tem que ser campos distintos: se um mapper
        // colapsar o rotulo completo em mainText, esta asserção quebra mesmo
        // que a fixture mude no futuro.
        $this->assertNotSame($primeira->description, $primeira->mainText);

        $this->assertIsString($primeira->secondaryText);
        $this->assertNotSame('', $primeira->secondaryText);
        $this->assertNotSame($primeira->mainText, $primeira->secondaryText);

        // O contrato de place=null diverge deliberadamente por provider: o
        // HERE tem itens chainQuery/categoryQuery, que sao refinamentos de
        // busca (ex.: "Postos Shell") sem id resolvivel via /lookup; o Google
        // nao tem equivalente e toda predicao carrega um placeId.
        if ($provider === Provider::Here) {
            $this->assertNotNull($primeira->place);
            $temSugestaoSemPlace = array_filter(
                $colecao->all(),
                fn ($sugestao) => $sugestao->place === null,
            ) !== [];
            $this->assertTrue(
                $temSugestaoSemPlace,
                'Esperava ao menos uma sugestao com place null (chainQuery) para o HERE.',
            );
        } else {
            foreach ($colecao as $sugestao) {
                $this->assertNotNull($sugestao->place);
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
