<?php

namespace BeeDelivery\BeeMaps\Tests\Feature;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\MapServiceFactory;
use BeeDelivery\BeeMaps\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class HereAutocompleteTest extends TestCase
{
    public function test_consulta_o_here_e_devolve_sugestoes_tipadas(): void
    {
        Http::fake([
            'autosuggest.search.hereapi.com/*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/here/autosuggest.json'), true),
                200,
            ),
        ]);

        $colecao = $this->app->make(MapServiceFactory::class)
            ->autocomplete(Provider::Here)
            ->suggest(new AutocompleteRequest('Av Paulista'));

        $this->assertCount(3, $colecao);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'apiKey=chave-here-de-teste'));
    }
}
