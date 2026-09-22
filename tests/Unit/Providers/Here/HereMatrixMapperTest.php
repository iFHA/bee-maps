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

        $colecao = (new HereMatrixResponseMapper())->toCollection($resposta, 2, 3);

        $this->assertCount(6, $colecao);

        // Row-major: indice = origem * numDestinations + destino. O par (1,0)
        // fica de fora aqui de proposito: a fixture o marca como inalcancavel, e
        // o teste seguinte cuida dele.
        $this->assertSame(1225, $colecao->entry(0, 0)->distance->meters);
        $this->assertSame(262, $colecao->entry(0, 0)->duration->seconds);
        $this->assertSame(1629, $colecao->entry(0, 1)->distance->meters);
        $this->assertSame(300, $colecao->entry(0, 1)->duration->seconds);
        $this->assertSame(2594, $colecao->entry(0, 2)->distance->meters);
        $this->assertSame(428, $colecao->entry(0, 2)->duration->seconds);
        $this->assertSame(26031, $colecao->entry(1, 1)->distance->meters);
        $this->assertSame(2290, $colecao->entry(1, 1)->duration->seconds);
        $this->assertSame(25021, $colecao->entry(1, 2)->distance->meters);
        $this->assertSame(2201, $colecao->entry(1, 2)->duration->seconds);
    }

    public function test_error_code_diferente_de_zero_marca_par_inalcancavel(): void
    {
        $resposta = json_decode(file_get_contents(__DIR__ . '/../../../Fixtures/here/matrix.json'), true);

        $colecao = (new HereMatrixResponseMapper())->toCollection($resposta, 2, 3);

        $this->assertFalse($colecao->entry(1, 0)->reachable);
        // A fixture traz 26089 metros nessa posicao, mas o errorCode 3 diz que
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
                'numDestinations' => 3,
                'travelTimes' => [262, 300, 428, 2317, 2290, 2201],
                'distances' => [1225, 1629, 2594, 26089, 26031, 25021],
            ],
        ];

        $colecao = (new HereMatrixResponseMapper())->toCollection($resposta, 2, 3);

        $this->assertCount(6, $colecao);

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
        (new HereMatrixResponseMapper())->toCollection(['matrixId' => 'abc', 'status' => 'pending'], 2, 2);
    }

    public function test_matriz_menor_que_o_pedido_vira_excecao(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/1x2|incompleta|dimens/i');

        (new HereMatrixResponseMapper())->toCollection([
            'matrix' => [
                'numOrigins' => 1,
                'numDestinations' => 2,
                'distances' => [100, 200],
                'travelTimes' => [10, 20],
            ],
        ], 2, 2);
    }

    public function test_dimensoes_trocadas_viram_excecao_mesmo_com_o_produto_certo(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/3x2|2x3|dimens/i');

        // 3x2 e 2x3 tem o mesmo produto: a guarda por contagem deixava passar,
        // e o resultado tinha origem fantasma (2,0) e par pedido faltando (0,2).
        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 3,
            'numDestinations' => 2,
            'distances' => [1, 2, 3, 4, 5, 6],
            'travelTimes' => [1, 2, 3, 4, 5, 6],
        ]], 2, 3);
    }

    public function test_distances_ausente_vira_excecao_em_vez_de_matriz_de_zero_metros(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/distances|medida/i');

        // Sem esta guarda o `?? 0` fabricava a matriz inteira como alcancavel
        // com 0 metros — a versao HERE do defeito que a correcao anterior
        // consertou no Google.
        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 2,
            'numDestinations' => 3,
            'travelTimes' => [1, 2, 3, 4, 5, 6],
        ]], 2, 3);
    }

    public function test_array_de_medida_mais_curto_que_a_grade_vira_excecao(): void
    {
        $this->expectException(ProviderRequestException::class);

        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 2,
            'numDestinations' => 3,
            'distances' => [1, 2, 3],
            'travelTimes' => [1, 2, 3, 4, 5, 6],
        ]], 2, 3);
    }

    public function test_error_codes_presente_e_curto_vira_excecao(): void
    {
        $this->expectException(ProviderRequestException::class);

        // errorCodes ausente significa "tudo alcancavel" e continua valendo;
        // errorCodes presente e curto faria o `?? 0` marcar como alcancavel
        // pares sobre os quais o HERE nao disse nada.
        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 2,
            'numDestinations' => 3,
            'distances' => [1, 2, 3, 4, 5, 6],
            'travelTimes' => [1, 2, 3, 4, 5, 6],
            'errorCodes' => [0, 0],
        ]], 2, 3);
    }

    public function test_campo_de_medida_escalar_vira_excecao_tipada_e_nao_type_error(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/lista|distances/i');

        // `??` so absorve null: sem is_array(), array_values() estoura TypeError,
        // que escapa do contrato de excecoes tipadas do pacote.
        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 1,
            'numDestinations' => 1,
            'distances' => 123,
            'travelTimes' => [1],
        ]], 1, 1);
    }

    public function test_medida_nao_numerica_vira_excecao_em_vez_de_zero_inventado(): void
    {
        $this->expectException(ProviderRequestException::class);
        $this->expectExceptionMessageMatches('/numero|numeric/i');

        // Contar nao basta: um null no meio de um array do tamanho certo passava
        // pela guarda e virava (int) null = 0 metros num par marcado como
        // alcancavel — exatamente a medida inventada que a rodada anterior
        // queria eliminar.
        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 1,
            'numDestinations' => 2,
            'distances' => [100, null],
            'travelTimes' => [10, 20],
        ]], 1, 2);
    }

    public function test_error_codes_escalar_vira_excecao_tipada(): void
    {
        $this->expectException(ProviderRequestException::class);

        (new HereMatrixResponseMapper())->toCollection(['matrix' => [
            'numOrigins' => 1,
            'numDestinations' => 1,
            'distances' => [100],
            'travelTimes' => [10],
            'errorCodes' => 'nenhum',
        ]], 1, 1);
    }
}
