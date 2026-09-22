<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntry;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\Tsp\NearestNeighbourTour;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class NearestNeighbourTourTest extends TestCase
{
    /**
     * Matriz completa a partir de posicoes numa reta: distancia em metros e o
     * modulo da diferenca, duracao em segundos e a distancia vezes 6. Assim os
     * dois criterios ordenam igual, e o teste que precisa separa-los monta a
     * matriz na mao.
     *
     * @param list<int|float> $posicoes
     */
    private function matrizNaReta(array $posicoes): RouteMatrixEntryCollection
    {
        $entradas = [];

        foreach ($posicoes as $o => $posOrigem) {
            foreach ($posicoes as $d => $posDestino) {
                $metros = (int) abs($posOrigem - $posDestino);

                $entradas[] = new RouteMatrixEntry(
                    originIndex: $o,
                    destinationIndex: $d,
                    distance: new Distance($metros),
                    duration: new Duration($metros * 6),
                    reachable: true,
                );
            }
        }

        return new RouteMatrixEntryCollection(...$entradas);
    }

    public function test_tour_aberto_nao_soma_volta_para_lugar_nenhum(): void
    {
        // Pontos: 0m, 100m, 200m, 300m. Aberto a partir do indice 0.
        $tour = (new NearestNeighbourTour())->tour(
            $this->matrizNaReta([0, 100, 200, 300]),
            origem: 0,
            fim: null,
            por: OptimizationObjective::MinDistance,
        );

        $this->assertSame([1, 2, 3], $tour->order);
        $this->assertSame(300, $tour->distance->meters);
        $this->assertSame(1800, $tour->duration->seconds);
    }

    public function test_fim_fixo_entra_nos_totais_e_fica_fora_da_ordem(): void
    {
        // Pontos: 0m, 100m, 200m, 300m. Fim pinado no indice 3.
        $tour = (new NearestNeighbourTour())->tour(
            $this->matrizNaReta([0, 100, 200, 300]),
            origem: 0,
            fim: 3,
            por: OptimizationObjective::MinDistance,
        );

        $this->assertSame([1, 2], $tour->order, 'o fim nao e resultado da otimizacao');
        $this->assertSame(300, $tour->distance->meters);
    }

    public function test_volta_a_origem_e_o_fim_apontando_para_a_propria_origem(): void
    {
        // destination = origin no contrato vira fim = indice da origem.
        $tour = (new NearestNeighbourTour())->tour(
            $this->matrizNaReta([0, 100, 200]),
            origem: 0,
            fim: 0,
            por: OptimizationObjective::MinDistance,
        );

        $this->assertSame([1, 2], $tour->order);
        // 0->100->200 e a volta 200->0.
        $this->assertSame(400, $tour->distance->meters);
    }

    public function test_origem_duplicada_no_fim_nao_sequestra_a_primeira_iteracao(): void
    {
        // Caso que o legado so sobrevivia por causa do filtro `distanceMeters <= 0`:
        // o mesmo ponto aparece nos indices 0 e 3, entao o par (0,3) mede 0 metros.
        // Aqui ele nunca e candidato porque 3 e o fim — invariante, nao proxy.
        $tour = (new NearestNeighbourTour())->tour(
            $this->matrizNaReta([0, 100, 200, 0]),
            origem: 0,
            fim: 3,
            por: OptimizationObjective::MinDistance,
        );

        $this->assertSame([1, 2], $tour->order);
        $this->assertSame(400, $tour->distance->meters);
    }

    public function test_duas_paradas_na_mesma_coordenada_nao_somem(): void
    {
        // Distancia 0 entre dois pontos DISTINTOS e medida valida: duas entregas
        // no mesmo predio existem. O legado descartava o par e a parada sumia.
        $tour = (new NearestNeighbourTour())->tour(
            $this->matrizNaReta([0, 100, 100, 300]),
            origem: 0,
            fim: null,
            por: OptimizationObjective::MinDistance,
        );

        $this->assertCount(3, $tour->order, 'nenhuma parada pode sumir');
        $this->assertSame([1, 2, 3], $tour->order);
        $this->assertSame(300, $tour->distance->meters);
    }

    public function test_objetivo_escolhe_o_criterio_de_ordenacao(): void
    {
        // Matriz montada a mao: de 0, o indice 1 e mais PERTO e o indice 2 e mais
        // RAPIDO. Os dois objetivos tem que divergir na primeira escolha.
        $entradas = [];
        $medidas = [
            // [origem, destino, metros, segundos]
            [0, 1, 100, 900], [0, 2, 500, 100],
            [1, 0, 100, 900], [1, 2, 400, 400],
            [2, 0, 500, 100], [2, 1, 400, 400],
        ];

        foreach ($medidas as [$o, $d, $m, $s]) {
            $entradas[] = new RouteMatrixEntry($o, $d, new Distance($m), new Duration($s), true);
        }

        foreach ([0, 1, 2] as $i) {
            $entradas[] = new RouteMatrixEntry($i, $i, new Distance(0), new Duration(0), true);
        }

        $matriz = new RouteMatrixEntryCollection(...$entradas);
        $tsp = new NearestNeighbourTour();

        $this->assertSame(
            [1, 2],
            $tsp->tour($matriz, 0, null, OptimizationObjective::MinDistance)->order,
        );

        $this->assertSame(
            [2, 1],
            $tsp->tour($matriz, 0, null, OptimizationObjective::MinTravelTime)->order,
        );
    }

    public function test_par_inalcancavel_nao_e_tratado_como_distancia_qualquer(): void
    {
        // Unico caminho de 0 sai para 1, e ele nao existe. Ordem que atravessa
        // trecho sem rota e pior que erro: chega ao entregador como itinerario
        // impossivel.
        $entradas = [
            new RouteMatrixEntry(0, 1, new Distance(0), new Duration(0), false),
            new RouteMatrixEntry(1, 0, new Distance(0), new Duration(0), false),
            new RouteMatrixEntry(0, 0, new Distance(0), new Duration(0), true),
            new RouteMatrixEntry(1, 1, new Distance(0), new Duration(0), true),
        ];

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/sem rota|inalcanc/i');

        (new NearestNeighbourTour())->tour(
            new RouteMatrixEntryCollection(...$entradas),
            origem: 0,
            fim: null,
            por: OptimizationObjective::MinDistance,
        );
    }

    public function test_perna_final_ausente_na_matriz_e_erro(): void
    {
        // A matriz nao traz o par ultima-parada -> fim. Fabricar 0 aqui inventaria
        // distancia; a regra do pacote e que medida ausente e erro.
        $entradas = [
            new RouteMatrixEntry(0, 1, new Distance(100), new Duration(600), true),
            new RouteMatrixEntry(0, 2, new Distance(200), new Duration(1200), true),
            new RouteMatrixEntry(1, 0, new Distance(100), new Duration(600), true),
            new RouteMatrixEntry(2, 0, new Distance(200), new Duration(1200), true),
            // O par (1, 2) — ultima parada -> fim — e justamente o que falta.
        ];

        $this->expectException(InvalidRequestException::class);

        (new NearestNeighbourTour())->tour(
            new RouteMatrixEntryCollection(...$entradas),
            origem: 0,
            fim: 2,
            por: OptimizationObjective::MinDistance,
        );
    }
}
