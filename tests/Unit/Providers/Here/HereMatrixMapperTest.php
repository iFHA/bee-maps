<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Providers\Here;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Exceptions\ProviderRequestException;
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

        $colecao = (new HereMatrixResponseMapper())->toCollection($resposta, 4);

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

        $colecao = (new HereMatrixResponseMapper())->toCollection($resposta, 4);

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

        $colecao = (new HereMatrixResponseMapper())->toCollection($resposta, 4);

        $this->assertCount(4, $colecao);

        foreach ($colecao as $entrada) {
            $this->assertTrue($entrada->reachable);
        }
    }

    public function test_resposta_sem_matriz_vira_excecao(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/matriz/i');

        // Envelope de job em vez de matriz: acontece se o async=false se perder
        // ou o HERE degradar uma requisicao grande. Devolver colecao vazia aqui
        // contraria o contrato — pares sem rota vem com reachable=false, nunca
        // ausentes — e o chamador nao distingue "sem matriz" de "matriz de nada".
        (new HereMatrixResponseMapper())->toCollection(['matrixId' => 'abc', 'status' => 'pending'], 4);
    }

    public function test_matriz_menor_que_o_pedido_vira_excecao(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/2 de 4|incompleta/i');

        (new HereMatrixResponseMapper())->toCollection([
            'matrix' => [
                'numOrigins' => 1,
                'numDestinations' => 2,
                'distances' => [100, 200],
                'travelTimes' => [10, 20],
            ],
        ], 4);
    }
}
