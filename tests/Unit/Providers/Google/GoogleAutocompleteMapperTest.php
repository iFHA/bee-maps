<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Google;

use BeeDelivery\BeeMaps\DTOs\Requests\AutocompleteRequest;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleAutocompleteResponseMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class GoogleAutocompleteMapperTest extends TestCase
{
    public function test_monta_payload_minimo_sem_coordenada(): void
    {
        $payload = (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista'),
            'pt-BR',
        );

        $this->assertSame('Av Paulista', $payload['input']);
        $this->assertSame('pt-BR', $payload['languageCode']);
        $this->assertArrayNotHasKey('locationRestriction', $payload);
    }

    public function test_inclui_restricao_circular_quando_ha_coordenada(): void
    {
        $payload = (new GoogleAutocompleteRequestMapper())->toPayload(
            new AutocompleteRequest('Av Paulista', new Coordinates(-23.5, -46.6), 3000, ['BR']),
            'pt-BR',
        );

        $this->assertSame(-23.5, $payload['locationRestriction']['circle']['center']['latitude']);
        $this->assertSame(3000, $payload['locationRestriction']['circle']['radius']);
        $this->assertSame(['BR'], $payload['includedRegionCodes']);
    }

    public function test_mapeia_resposta_para_sugestoes(): void
    {
        $json = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/autocomplete.json'), true);

        $colecao = (new GoogleAutocompleteResponseMapper())->toCollection($json);

        $this->assertCount(2, $colecao);

        $primeira = $colecao->first();
        $this->assertSame('Avenida Paulista, 1000', $primeira->mainText);
        $this->assertSame(Provider::Google, $primeira->place->provider);
        $this->assertSame('ChIJ0WGkg4FEzpQRrlsz_whLqZs', $primeira->place->id);
        $this->assertFalse($primeira->isEstablishment);

        $this->assertTrue($colecao->all()[1]->isEstablishment);
    }

    public function test_resposta_sem_sugestoes_devolve_colecao_vazia(): void
    {
        $colecao = (new GoogleAutocompleteResponseMapper())->toCollection([]);

        $this->assertTrue($colecao->isEmpty());
    }
}
