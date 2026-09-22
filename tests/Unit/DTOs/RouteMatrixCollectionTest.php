<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\DTOs;

use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntry;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Exceptions\MatrixTooLargeException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class RouteMatrixCollectionTest extends TestCase
{
    private function entrada(int $origem, int $destino, int $metros): RouteMatrixEntry
    {
        return new RouteMatrixEntry($origem, $destino, new Distance($metros), new Duration(60), true);
    }

    public function test_colecao_vazia(): void
    {
        $colecao = new RouteMatrixEntryCollection();

        $this->assertTrue($colecao->isEmpty());
        $this->assertNull($colecao->first());
        $this->assertNull($colecao->entry(0, 0));
    }

    public function test_entrada_e_endereçavel_por_par_mesmo_fora_de_ordem(): void
    {
        // Ordem literal que o Google devolveu numa matriz 2x2 real.
        $colecao = new RouteMatrixEntryCollection(
            $this->entrada(0, 1, 1807),
            $this->entrada(0, 0, 974),
            $this->entrada(1, 0, 1371),
            $this->entrada(1, 1, 646),
        );

        $this->assertCount(4, $colecao);
        $this->assertSame(974, $colecao->entry(0, 0)->distance->meters);
        $this->assertSame(1807, $colecao->entry(0, 1)->distance->meters);
        $this->assertSame(1371, $colecao->entry(1, 0)->distance->meters);
        $this->assertSame(646, $colecao->entry(1, 1)->distance->meters);
        $this->assertNull($colecao->entry(9, 9));
    }

    public function test_request_conta_elementos(): void
    {
        $request = new RouteMatrixRequest(
            [new Coordinates(-23.5, -46.6), new Coordinates(-23.6, -46.7)],
            [new Coordinates(-23.55, -46.65), new Coordinates(-23.58, -46.68), new Coordinates(-23.59, -46.69)],
        );

        $this->assertSame(6, $request->elements());
    }

    public function test_request_sem_origem_ou_sem_destino_e_rejeitado(): void
    {
        $this->expectException(InvalidRequestException::class);

        new RouteMatrixRequest([], [new Coordinates(-23.5, -46.6)]);
    }

    public function test_excecao_de_matriz_grande_diz_o_numero_e_o_limite(): void
    {
        $excecao = MatrixTooLargeException::make(Provider::Google, 900, 625);

        $this->assertInstanceOf(InvalidRequestException::class, $excecao);
        $this->assertStringContainsString('900', $excecao->getMessage());
        $this->assertStringContainsString('625', $excecao->getMessage());
    }

    public function test_pontos_com_chaves_nao_sequenciais_sao_reindexados(): void
    {
        $pontos = [
            new Coordinates(-23.5, -46.6),
            new Coordinates(-23.6, -46.7),
            new Coordinates(-23.7, -46.8),
        ];

        // array_filter preserva as chaves originais: [0, 2]. Sem reindexar, o
        // json_encode do payload vira objeto ({"0":...,"2":...}) em vez de array
        // e os dois providers respondem 400 com mensagem opaca — e os indices do
        // resultado deixam de casar com as chaves que o chamador enxerga.
        $filtrados = array_filter($pontos, fn (Coordinates $p) => $p->latitude !== -23.6);

        $request = new RouteMatrixRequest($filtrados, [new Coordinates(-23.55, -46.65)]);

        $this->assertSame([0, 1], array_keys($request->origins));
        $this->assertTrue(array_is_list($request->origins));
        $this->assertTrue(array_is_list($request->destinations));
        $this->assertSame(-23.7, $request->origins[1]->latitude);
    }
}
