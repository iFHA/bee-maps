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
    private function requisicao(): RouteMatrixRequest
    {
        return new RouteMatrixRequest(
            [new Coordinates(-23.5615, -46.6562), new Coordinates(-23.5505, -46.6425)],
            [new Coordinates(-23.5580, -46.6500), new Coordinates(-23.5540, -46.6470)],
            TravelMode::TwoWheeler,
        );
    }

    public function test_payload_envolve_cada_ponto_em_waypoint(): void
    {
        $payload = (new GoogleRouteMatrixRequestMapper())->toPayload($this->requisicao());

        $this->assertCount(2, $payload['origins']);
        $this->assertCount(2, $payload['destinations']);
        $this->assertSame(-23.5615, $payload['origins'][0]['waypoint']['location']['latLng']['latitude']);
        $this->assertSame(-46.6500, $payload['destinations'][0]['waypoint']['location']['latLng']['longitude']);
        $this->assertSame('TWO_WHEELER', $payload['travelMode']);
    }

    public function test_field_mask_nao_usa_o_prefixo_routes(): void
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

    public function test_resposta_fora_de_ordem_vira_colecao_endereçavel(): void
    {
        $resposta = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/route-matrix.json'), true);

        $colecao = (new GoogleRouteMatrixResponseMapper())->toCollection($resposta, 4);

        $this->assertCount(4, $colecao);

        // A fixture esta na ordem que a API devolveu: (0,1) antes de (0,0).
        // Se o mapper indexasse por posicao, estes valores sairiam trocados.
        $this->assertSame(974, $colecao->entry(0, 0)->distance->meters);
        $this->assertSame(271, $colecao->entry(0, 0)->duration->seconds);
        $this->assertSame(1807, $colecao->entry(0, 1)->distance->meters);
        $this->assertSame(646, $colecao->entry(1, 1)->distance->meters);
    }

    public function test_par_sem_rota_vira_entrada_inalcancavel_e_nao_some(): void
    {
        $resposta = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/route-matrix.json'), true);

        $entrada = (new GoogleRouteMatrixResponseMapper())->toCollection($resposta, 4)->entry(1, 0);

        $this->assertNotNull($entrada);
        $this->assertFalse($entrada->reachable);
        $this->assertSame(0, $entrada->distance->meters);
        $this->assertSame(0, $entrada->duration->seconds);
    }

    public function test_resposta_vazia_vira_colecao_vazia(): void
    {
        $this->assertTrue((new GoogleRouteMatrixResponseMapper())->toCollection([], 0)->isEmpty());
    }

    public function test_elemento_de_erro_no_stream_vira_excecao_em_vez_de_par_falso(): void
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
        ], 2);
    }

    public function test_condition_ausente_nao_conta_como_alcancavel(): void
    {
        // proto3 omite o valor default do enum, e
        // ROUTE_MATRIX_ELEMENT_CONDITION_UNSPECIFIED vale 0: ausencia significa
        // "indefinido", nao "tem rota".
        $colecao = (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0],
        ], 1);

        $this->assertFalse($colecao->entry(0, 0)->reachable);
    }

    public function test_stream_truncado_vira_excecao(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/incompleta|1 de 4|elementos/i');

        (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 974, 'duration' => '271s', 'condition' => 'ROUTE_EXISTS'],
        ], 4);
    }
}
