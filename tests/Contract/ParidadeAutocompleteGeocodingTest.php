<?php

namespace BeeDelivery\BeeMaps\Tests\Contract;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\GeocodeResultCollection;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
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
        $fixture = fn (string $path) => json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/' . $path),
            true,
        );

        Http::fake([
            'places.googleapis.com/*' => Http::response($fixture('google/autocomplete.json'), 200),
            'maps.googleapis.com/*' => Http::response($fixture('google/geocode.json'), 200),
            'autosuggest.search.hereapi.com/*' => Http::response($fixture('here/autosuggest.json'), 200),
            'autocomplete.search.hereapi.com/*' => Http::response($fixture('here/autocomplete.json'), 200),
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

        // Com `near` o HERE vai para o /autosuggest, que e o caminho com POI e
        // com itens nao resolviveis — as duas divergencias fixadas abaixo. O
        // caminho sem foco tem teste proprio logo a seguir.
        $collection = $this->app->make(MapServiceFactory::class)
            ->autocomplete($provider)
            ->suggest(new AutocompleteRequest('Av Paulista', new Coordinates(-23.5615, -46.6562)));

        $this->assertInstanceOf(SuggestionCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count());

        foreach ($collection as $suggestion) {
            $this->assertNotSame('', $suggestion->description);
            $this->assertNotSame('', $suggestion->mainText);
            $this->assertIsBool($suggestion->isEstablishment);

            if ($suggestion->place !== null) {
                $this->assertSame($provider, $suggestion->place->provider);
                $this->assertNotSame('', $suggestion->place->id);
            }
        }

        $first = $collection->first();

        // mainText converge para o mesmo valor nos dois providers: e o campo
        // estruturado (titulo do HERE / structuredFormat.mainText do Google).
        // description e secondaryText legitimamente diferem em pontuacao e
        // conteudo entre providers, por isso nao sao comparados entre si aqui.
        $this->assertSame('Avenida Paulista, 1000', $first->mainText);

        // mainText e description tem que ser campos distintos: se um mapper
        // colapsar o rotulo completo em mainText, esta asserção quebra mesmo
        // que a fixture mude no futuro.
        $this->assertNotSame($first->description, $first->mainText);

        $this->assertIsString($first->secondaryText);
        $this->assertNotSame('', $first->secondaryText);
        $this->assertNotSame($first->mainText, $first->secondaryText);

        // Google devolve secondaryText como campo estruturado; HERE o deriva
        // removendo a linha principal do label completo. Os dois convergiram no
        // formato, mas o HERE inclui o CEP — divergencia de conteudo, nao de
        // estrategia, entao pinamos os dois valores.
        if ($provider === Provider::Here) {
            $this->assertSame('Bela Vista, Sao Paulo - SP, 01310-100, Brasil', $first->secondaryText);
        } else {
            $this->assertSame('Bela Vista, Sao Paulo - SP, Brasil', $first->secondaryText);
        }

        // O contrato de place=null diverge deliberadamente por provider: o
        // HERE tem itens chainQuery/categoryQuery, que sao refinamentos de
        // busca (ex.: "Postos Shell") sem id resolvivel via /lookup; o Google
        // nao tem equivalente e toda predicao carrega um placeId.
        if ($provider === Provider::Here) {
            $this->assertNotNull($first->place);
            $hasSuggestionWithoutPlace = array_filter(
                $collection->all(),
                fn ($suggestion) => $suggestion->place === null,
            ) !== [];
            $this->assertTrue(
                $hasSuggestionWithoutPlace,
                'Esperava ao menos uma sugestao com place null (chainQuery) para o HERE.',
            );
        } else {
            foreach ($collection as $suggestion) {
                $this->assertNotNull($suggestion->place);
            }
        }
    }

    /**
     * A busca sem ponto de referencia (cadastro de empresa, endereco em outro
     * estado) troca de endpoint no HERE — /autocomplete em vez de /autosuggest
     * — e o contrato tem que sobreviver a essa troca.
     */
    #[DataProvider('providers')]
    public function test_autocomplete_sem_foco_devolve_o_mesmo_contrato(Provider $provider): void
    {
        $this->fakeTudo();

        $collection = $this->app->make(MapServiceFactory::class)
            ->autocomplete($provider)
            ->suggest(new AutocompleteRequest('Av Paulista'));

        $this->assertInstanceOf(SuggestionCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count());

        foreach ($collection as $suggestion) {
            $this->assertNotSame('', $suggestion->description);
            $this->assertNotSame('', $suggestion->mainText);
            $this->assertIsBool($suggestion->isEstablishment);
            $this->assertNotSame($suggestion->description, $suggestion->mainText);

            // Aqui os dois providers convergem no que o /autosuggest diverge:
            // sem chainQuery/categoryQuery, toda sugestao resolve no lookup.
            $this->assertNotNull($suggestion->place);
            $this->assertSame($provider, $suggestion->place->provider);
            $this->assertNotSame('', $suggestion->place->id);
        }

        $this->assertSame('Avenida Paulista, 1000', $collection->first()->mainText);

        // Divergencia assumida: o /autocomplete do HERE nao tem resultType de
        // lugar, entao nunca marca estabelecimento. O Google marca nos dois
        // caminhos. Quem ramifica em isEstablishment precisa saber disso.
        if ($provider === Provider::Here) {
            foreach ($collection as $suggestion) {
                $this->assertFalse($suggestion->isEstablishment);
            }
        }
    }

    #[DataProvider('providers')]
    public function test_geocode_devolve_o_mesmo_contrato(Provider $provider): void
    {
        $this->fakeTudo();

        $collection = $this->app->make(MapServiceFactory::class)
            ->geocoding($provider)
            ->geocode('Av Paulista 1000');

        $this->assertInstanceOf(GeocodeResultCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count());

        $result = $collection->first();

        $this->assertSame('Avenida Paulista', $result->address->street);
        $this->assertSame('1000', $result->address->number);
        $this->assertSame('Bela Vista', $result->address->neighborhood);
        $this->assertSame('Sao Paulo', $result->address->city);
        $this->assertSame('SP', $result->address->state);
        $this->assertSame('Brasil', $result->address->country);
        $this->assertSame('01310100', $result->address->postalCode);
        // formatted diverge legitimamente entre providers, entao so garantimos
        // que existe - nao pinamos um valor literal.
        $this->assertNotSame('', $result->address->formatted);
        $this->assertEqualsWithDelta(-23.5615, $result->coordinates->latitude, 0.0001);
        $this->assertIsBool($result->partial);
        $this->assertSame($provider, $result->place->provider);

        // Divergencia conhecida e deliberada: matchScore existe so onde o provider
        // reporta grau de casamento. Ver a nota da Task 11.
        $provider === Provider::Google
            ? $this->assertNull($result->matchScore)
            : $this->assertIsFloat($result->matchScore);
    }
}
