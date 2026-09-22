<?php

namespace BeeDelivery\BeeMaps\Support\Tsp;

use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntry;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;

/**
 * Vizinho-mais-proximo sobre uma matriz de rotas. Nao conhece provider nem
 * contrato: recebe a matriz e indices, devolve indices. E isso que o torna
 * testavel com matriz montada a mao, sem HTTP.
 *
 * A diagonal nao precisa de filtro: o laco so consulta entry($atual, $candidato)
 * com $candidato entre os pendentes, e $atual nunca esta la. O $fim tambem fica
 * fora dos pendentes — por isso o ponto duplicado do caso `destination = origin`
 * nao vira candidato, sem depender de medir 0 metros para ser reconhecido.
 */
final class NearestNeighbourTour
{
    /**
     * @param int  $origem Indice do ponto de partida na matriz.
     * @param ?int $fim    Indice do ponto final, ou null para tour aberto. Pode
     *                     ser igual a $origem: e assim que "volta ao ponto de
     *                     partida" se expressa.
     *
     * @throws InvalidRequestException quando nao ha como avancar ou a perna final
     *         nao existe na matriz
     */
    public function tour(
        RouteMatrixEntryCollection $matriz,
        int $origem,
        ?int $fim,
        OptimizationObjective $por,
    ): TourResult {
        $pendentes = $this->pontosAVisitar($matriz, $origem, $fim);

        $ordem = [];
        $metros = 0;
        $segundos = 0;
        $atual = $origem;

        while ($pendentes !== []) {
            $proximo = $this->maisProximo($matriz, $atual, $pendentes, $por);
            $perna = $matriz->entry($atual, $proximo);

            $metros += $perna->distance->meters;
            $segundos += $perna->duration->seconds;
            $ordem[] = $proximo;

            $pendentes = array_values(array_filter(
                $pendentes,
                static fn (int $no): bool => $no !== $proximo,
            ));

            $atual = $proximo;
        }

        if ($fim !== null) {
            $perna = $matriz->entry($atual, $fim);

            // Fabricar 0 aqui inventaria distancia. Medida ausente e erro.
            if ($perna === null || ! $perna->reachable) {
                throw new InvalidRequestException(sprintf(
                    'A matriz nao traz rota entre o ponto %d e o destino %d; '
                    . 'somar zero inventaria a perna final.',
                    $atual,
                    $fim,
                ));
            }

            $metros += $perna->distance->meters;
            $segundos += $perna->duration->seconds;
        }

        return new TourResult($ordem, new Distance($metros), new Duration($segundos));
    }

    /**
     * @return list<int>
     */
    private function pontosAVisitar(RouteMatrixEntryCollection $matriz, int $origem, ?int $fim): array
    {
        $nos = [];

        foreach ($matriz as $entrada) {
            $nos[$entrada->originIndex] = true;
            $nos[$entrada->destinationIndex] = true;
        }

        $pendentes = array_filter(
            array_keys($nos),
            static fn (int $no): bool => $no !== $origem && $no !== $fim,
        );

        sort($pendentes);

        return array_values($pendentes);
    }

    /**
     * @param list<int> $pendentes
     */
    private function maisProximo(
        RouteMatrixEntryCollection $matriz,
        int $atual,
        array $pendentes,
        OptimizationObjective $por,
    ): int {
        $escolhido = null;
        $melhor = null;

        foreach ($pendentes as $candidato) {
            $perna = $matriz->entry($atual, $candidato);

            // Par sem rota nao entra na escolha: usar a medida zerada que o
            // contrato da matriz poe em reachable=false faria dele o mais
            // proximo de todos.
            if ($perna === null || ! $perna->reachable) {
                continue;
            }

            $custo = $this->custo($perna, $por);

            if ($melhor === null || $custo < $melhor) {
                $melhor = $custo;
                $escolhido = $candidato;
            }
        }

        return $escolhido ?? throw new InvalidRequestException(sprintf(
            'Nenhum dos %d pontos restantes e alcancavel a partir do ponto %d; '
            . 'ordem que atravessa trecho sem rota e pior que erro.',
            count($pendentes),
            $atual,
        ));
    }

    private function custo(RouteMatrixEntry $perna, OptimizationObjective $por): int
    {
        return match ($por) {
            OptimizationObjective::MinDistance => $perna->distance->meters,
            OptimizationObjective::MinTravelTime => $perna->duration->seconds,
        };
    }
}
