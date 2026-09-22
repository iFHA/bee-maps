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

        $colecao = (new GoogleRouteMatrixResponseMapper())->toCollection($resposta, 2, 3);

        $this->assertCount(6, $colecao);

        // A fixture esta na ordem que a API devolveu: (0,1) antes de (0,0).
        // Se o mapper indexasse por posicao, estes valores sairiam trocados.
        $this->assertSame(1225, $colecao->entry(0, 0)->distance->meters);
        $this->assertSame(262, $colecao->entry(0, 0)->duration->seconds);
        $this->assertSame(1629, $colecao->entry(0, 1)->distance->meters);
        $this->assertSame(2594, $colecao->entry(0, 2)->distance->meters);
        $this->assertSame(26031, $colecao->entry(1, 1)->distance->meters);
        $this->assertSame(25021, $colecao->entry(1, 2)->distance->meters);
    }

    public function test_par_sem_rota_vira_entrada_inalcancavel_e_nao_some(): void
    {
        $resposta = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/google/route-matrix.json'), true);

        $entrada = (new GoogleRouteMatrixResponseMapper())->toCollection($resposta, 2, 3)->entry(1, 0);

        $this->assertNotNull($entrada);
        $this->assertFalse($entrada->reachable);
        $this->assertSame(0, $entrada->distance->meters);
        $this->assertSame(0, $entrada->duration->seconds);
    }

    public function test_resposta_vazia_vira_colecao_vazia(): void
    {
        $this->assertTrue((new GoogleRouteMatrixResponseMapper())->toCollection([], 0, 0)->isEmpty());
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
        ], 1, 2);
    }

    public function test_condition_ausente_nao_conta_como_alcancavel(): void
    {
        // proto3 omite o valor default do enum, e
        // ROUTE_MATRIX_ELEMENT_CONDITION_UNSPECIFIED vale 0: ausencia significa
        // "indefinido", nao "tem rota".
        $colecao = (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0],
        ], 1, 1);

        $this->assertFalse($colecao->entry(0, 0)->reachable);
    }

    public function test_stream_truncado_vira_excecao(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/incompleta|1 de 4|elementos/i');

        (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 974, 'duration' => '271s', 'condition' => 'ROUTE_EXISTS'],
        ], 2, 2);
    }

    public function test_par_duplicado_vira_excecao_mesmo_com_a_contagem_certa(): void
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

    public function test_indice_fora_da_faixa_pedida_vira_excecao(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/fora da faixa|indice/i');

        (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 10, 'duration' => '1s', 'condition' => 'ROUTE_EXISTS'],
            ['originIndex' => 5, 'destinationIndex' => 0, 'distanceMeters' => 20, 'duration' => '2s', 'condition' => 'ROUTE_EXISTS'],
        ], 1, 2);
    }

    public function test_grade_completa_passa(): void
    {
        $colecao = (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 1, 'distanceMeters' => 20, 'duration' => '2s', 'condition' => 'ROUTE_EXISTS'],
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 10, 'duration' => '1s', 'condition' => 'ROUTE_EXISTS'],
        ], 1, 2);

        $this->assertCount(2, $colecao);
        $this->assertSame(10, $colecao->entry(0, 0)->distance->meters);
        $this->assertSame(20, $colecao->entry(0, 1)->distance->meters);
    }

    public function test_erro_no_topo_do_corpo_vira_excecao_e_nao_par_sem_rota(): void
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

    public function test_matriz_normal_continua_passando(): void
    {
        $colecao = (new GoogleRouteMatrixResponseMapper())->toCollection([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 10, 'duration' => '1s', 'condition' => 'ROUTE_EXISTS'],
        ], 1, 1);

        $this->assertSame(10, $colecao->entry(0, 0)->distance->meters);
    }
}
