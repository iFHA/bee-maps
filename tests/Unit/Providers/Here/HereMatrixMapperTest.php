<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereMatrixRequestMapper;
use BeeDelivery\BeeMaps\Providers\Here\Mappers\HereMatrixResponseMapper;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class HereMatrixMapperTest extends TestCase
{
    private function requisicao(): RouteMatrixRequest
    {
        return new RouteMatrixRequest(
            [new Coordinates(-23.5615, -46.6562), new Coordinates(-23.5505, -46.6425)],
            [new Coordinates(-23.5580, -46.6500), new Coordinates(-23.5540, -46.6470)],
            TravelMode::TwoWheeler,
        );
    }

    public function test_payload_usa_auto_circle_e_pede_distancia_e_tempo(): void
    {
        $payload = (new HereMatrixRequestMapper())->toPayload($this->requisicao());

        $this->assertSame(['lat' => -23.5615, 'lng' => -46.6562], $payload['origins'][0]);
        $this->assertSame(['lat' => -23.5580, 'lng' => -46.6500], $payload['destinations'][0]);
        // O HERE calcula o circulo sozinho e devolve o que usou; nao e preciso
        // derivar centro e raio dos pontos no cliente.
        $this->assertSame(['type' => 'autoCircle'], $payload['regionDefinition']);
        $this->assertSame(['distances', 'travelTimes'], $payload['matrixAttributes']);
        $this->assertSame('scooter', $payload['transportMode']);
    }

    public function test_arrays_achatados_viram_entradas_por_par(): void
    {
        $resposta = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/matrix.json'), true);

        $colecao = (new HereMatrixResponseMapper())->toCollection($resposta);

        $this->assertCount(4, $colecao);

        // Row-major: indice = origem * numDestinations + destino. O par (1,0)
        // fica de fora aqui de proposito: a fixture o marca como inalcancavel, e
        // o teste seguinte cuida dele.
        $this->assertSame(1225, $colecao->entry(0, 0)->distance->meters);
        $this->assertSame(262, $colecao->entry(0, 0)->duration->seconds);
        $this->assertSame(1629, $colecao->entry(0, 1)->distance->meters);
        $this->assertSame(300, $colecao->entry(0, 1)->duration->seconds);
        $this->assertSame(1915, $colecao->entry(1, 1)->distance->meters);
        $this->assertSame(466, $colecao->entry(1, 1)->duration->seconds);
    }

    public function test_error_code_diferente_de_zero_marca_par_inalcancavel(): void
    {
        $resposta = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/matrix.json'), true);

        $colecao = (new HereMatrixResponseMapper())->toCollection($resposta);

        $this->assertFalse($colecao->entry(1, 0)->reachable);
        // A fixture traz 1511 metros nessa posicao, mas o errorCode 3 diz que
        // nao ha rota: o contrato zera a medida em vez de propagar um numero
        // que nao corresponde a percurso nenhum.
        $this->assertSame(0, $colecao->entry(1, 0)->distance->meters);
        $this->assertSame(0, $colecao->entry(1, 0)->duration->seconds);
        $this->assertTrue($colecao->entry(0, 0)->reachable);
    }

    public function test_ausencia_de_error_codes_significa_tudo_alcancavel(): void
    {
        // Resposta real do HERE quando nao ha par inalcancavel: o campo
        // errorCodes simplesmente nao vem. Tratar ausencia como erro marcaria
        // a matriz inteira como inalcancavel.
        $resposta = [
            'matrix' => [
                'numOrigins' => 2,
                'numDestinations' => 2,
                'travelTimes' => [262, 300, 428, 466],
                'distances' => [1225, 1629, 1511, 1915],
            ],
        ];

        $colecao = (new HereMatrixResponseMapper())->toCollection($resposta);

        $this->assertCount(4, $colecao);

        foreach ($colecao as $entrada) {
            $this->assertTrue($entrada->reachable);
        }
    }

    public function test_resposta_sem_matriz_vira_colecao_vazia(): void
    {
        $this->assertTrue((new HereMatrixResponseMapper())->toCollection([])->isEmpty());
    }
}
