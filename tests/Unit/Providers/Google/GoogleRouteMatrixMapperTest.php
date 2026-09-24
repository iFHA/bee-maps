<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Google;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteMatrixRequestMapper;
use BeeDelivery\BeeMaps\Providers\Google\Mappers\GoogleRouteMatrixResponseMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class GoogleRouteMatrixMapperTest extends TestCase
{
    private function request(): RouteMatrixRequest
    {
        return new RouteMatrixRequest(
            [new Coordinates(-23.5615, -46.6562), new Coordinates(-23.5505, -46.6425)],
            [new Coordinates(-23.5580, -46.6500), new Coordinates(-23.5540, -46.6470)],
            TravelMode::TwoWheeler,
        );
    }

    public function test_the_payload_wraps_every_point_in_a_waypoint(): void
    {
        $payload = (new GoogleRouteMatrixRequestMapper())->toPayload($this->request());

        $this->assertCount(2, $payload['origins']);
        $this->assertCount(2, $payload['destinations']);
        $this->assertSame(-23.5615, $payload['origins'][0]['waypoint']['location']['latLng']['latitude']);
        $this->assertSame(-46.6500, $payload['destinations'][0]['waypoint']['location']['latLng']['longitude']);
        $this->assertSame('TWO_WHEELER', $payload['travelMode']);
    }

    public function test_the_field_mask_does_not_use_the_routes_prefix(): void
    {
        $mask = (new GoogleRouteMatrixRequestMapper())->fieldMask();

        // A resposta do computeRouteMatrix e um array de elementos na raiz, nao
        // um objeto com "routes": prefixar com "routes." devolve 400.
        $this->assertStringNotContainsString('routes.', $mask);
        $this->assertStringContainsString('originIndex', $mask);
        $this->assertStringContainsString('destinationIndex', $mask);
        $this->assertStringContainsString('distanceMeters', $mask);
        $this->assertStringContainsString('duration', $mask);
        $this->assertStringContainsString('condition', $mask);
    }

    public function test_an_out_of_order_response_becomes_an_addressable_collection(): void
    {
        $response = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/route-matrix.json'), true);

        $collection = (new GoogleRouteMatrixResponseMapper())->toCollection($response, 2, 3);

        $this->assertCount(6, $collection);

        // A fixture esta na ordem que a API devolveu: (0,1) antes de (0,0).
        // Se o mapper indexasse por posicao, estes valores sairiam trocados.
        $this->assertSame(1225, $collection->entry(0, 0)->distance->meters);
        $this->assertSame(262, $collection->entry(0, 0)->duration->seconds);
        $this->assertSame(1629, $collection->entry(0, 1)->distance->meters);
        $this->assertSame(2594, $collection->entry(0, 2)->distance->meters);
        $this->assertSame(26031, $collection->entry(1, 1)->distance->meters);
        $this->assertSame(25021, $collection->entry(1, 2)->distance->meters);
    }

    public function test_a_pair_without_a_route_becomes_an_unreachable_entry_and_does_not_vanish(): void
    {
        $response = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/route-matrix.json'), true);

        $entry = (new GoogleRouteMatrixResponseMapper())->toCollection($response, 2, 3)->entry(1, 0);

        $this->assertNotNull($entry);
        $this->assertFalse($entry->reachable);
        $this->assertSame(0, $entry->distance->meters);
        $this->assertSame(0, $entry->duration->seconds);
    }

    public function test_an_empty_response_becomes_an_empty_collection(): void
    {
        $this->assertTrue((new GoogleRouteMatrixResponseMapper())->toCollection([], 0, 0)->isEmpty());
    }

    public function test_an_error_element_in_the_stream_becomes_an_exception_instead_of_a_fake_pair(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/internal|erro/i');

        // O computeRouteMatrix e server-streaming: quando falha DEPOIS do stream
        // comecar, o Google anexa o erro como ultimo elemento com HTTP 200. Sem
        // este tratamento o elemento virava um par (0,0) de 0 metros alcancavel,
        // sobrescrevendo a entrada verdadeira.
        (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 974, 'duration' => '271s', 'condition' => 'ROUTE_EXISTS'],
            ['error' => ['code' => 500, 'message' => 'internal']],
        ], 1, 2);
    }

    public function test_a_missing_condition_does_not_count_as_reachable(): void
    {
        // proto3 omite o valor default do enum, e
        // ROUTE_MATRIX_ELEMENT_CONDITION_UNSPECIFIED vale 0: ausencia significa
        // "indefinido", nao "tem rota".
        $collection = (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0],
        ], 1, 1);

        $this->assertFalse($collection->entry(0, 0)->reachable);
    }

    public function test_a_truncated_stream_becomes_an_exception(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/incompleta|1 de 4|elementos/i');

        (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 974, 'duration' => '271s', 'condition' => 'ROUTE_EXISTS'],
        ], 2, 2);
    }

    public function test_a_duplicated_pair_becomes_an_exception_even_with_the_right_count(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/duplicad|repetid/i');

        // Contar elementos nao basta: dois elementos para (0,0) somam 2 e
        // deixam (0,1) sem nenhum. A colecao indexa por par, entao o duplicado
        // sobrescreve o primeiro e o par pedido vira null.
        (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 10, 'duration' => '1s', 'condition' => 'ROUTE_EXISTS'],
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 20, 'duration' => '2s', 'condition' => 'ROUTE_EXISTS'],
        ], 1, 2);
    }

    public function test_an_index_outside_the_requested_range_becomes_an_exception(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/fora da faixa|indice/i');

        (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 10, 'duration' => '1s', 'condition' => 'ROUTE_EXISTS'],
            ['originIndex' => 5, 'destinationIndex' => 0, 'distanceMeters' => 20, 'duration' => '2s', 'condition' => 'ROUTE_EXISTS'],
        ], 1, 2);
    }

    public function test_a_complete_grid_passes(): void
    {
        $collection = (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 1, 'distanceMeters' => 20, 'duration' => '2s', 'condition' => 'ROUTE_EXISTS'],
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 10, 'duration' => '1s', 'condition' => 'ROUTE_EXISTS'],
        ], 1, 2);

        $this->assertCount(2, $collection);
        $this->assertSame(10, $collection->entry(0, 0)->distance->meters);
        $this->assertSame(20, $collection->entry(0, 1)->distance->meters);
    }

    public function test_a_top_level_error_in_the_body_becomes_an_exception_not_an_unrouted_pair(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/internal/i');

        // Com HTTP 200 o traduzirErro do MapsHttpClient nunca roda, e sem esta
        // guarda o corpo era iterado como se fosse elemento de matriz: as chaves
        // viram code/message/status, os indices caem em (0,0) e a grade 1x1 fica
        // "completa" — o erro da API virava um fato de roteirizacao.
        (new GoogleRouteMatrixResponseMapper())->toCollection(
            ['error' => ['code' => 500, 'message' => 'internal', 'status' => 'INTERNAL']],
            1,
            1,
        );
    }

    public function test_a_normal_matrix_still_passes(): void
    {
        $collection = (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 10, 'duration' => '1s', 'condition' => 'ROUTE_EXISTS'],
        ], 1, 1);

        $this->assertSame(10, $collection->entry(0, 0)->distance->meters);
    }

    public function test_an_error_of_an_unexpected_type_also_becomes_an_exception(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/boom/');

        // Exigir array aqui estreitava a guarda: um `error` de outro tipo voltava
        // a ser lido como elemento de matriz e virava par fantasma em (0,0).
        (new GoogleRouteMatrixResponseMapper())->toCollection([['error' => 'boom']], 1, 1);
    }

    public function test_a_top_level_error_with_an_unexpected_type_also_becomes_an_exception(): void
    {
        $this->expectException(ProviderRequestException::class);

        (new GoogleRouteMatrixResponseMapper())->toCollection(['error' => 'boom'], 1, 1);
    }

    public function test_a_credential_in_the_provider_message_is_redacted(): void
    {
        try {
            (new GoogleRouteMatrixResponseMapper())->toCollection(
                ['error' => ['code' => 400, 'message' => 'falha em https://x/y?key=SEGREDO&a=1']],
                1,
                1,
            );

            $this->fail('Esperava ProviderRequestException.');
        } catch (ProviderRequestException $e) {
            // Texto cru de provider pode ecoar a URL, e a query do Google leva
            // `key=`. Sem redigir aqui a chave vaza para o log por um caminho
            // que nao passa pelo MapsHttpClient.
            $this->assertStringNotContainsString('SEGREDO', $e->getMessage());
            $this->assertStringContainsString('key=[REDACTED]', $e->getMessage());
            // Mesmo fallback do MapsHttpClient: sem `status`, usa `code`.
            $this->assertSame('400', $e->providerCode());
        }
    }

    public function test_status_takes_precedence_over_code_in_the_provider_code(): void
    {
        try {
            (new GoogleRouteMatrixResponseMapper())->toCollection(
                ['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'm']],
                1,
                1,
            );

            $this->fail('Esperava ProviderRequestException.');
        } catch (ProviderRequestException $e) {
            $this->assertSame('INVALID_ARGUMENT', $e->providerCode());
        }
    }
}
