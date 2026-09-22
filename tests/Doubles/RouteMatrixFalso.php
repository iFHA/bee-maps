<?php

namespace BeeDelivery\BeeMaps\Tests\Doubles;

use BeeDelivery\BeeMaps\Contracts\Services\RouteMatrix;
use BeeDelivery\BeeMaps\DTOs\Requests\RouteMatrixRequest;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntry;
use BeeDelivery\BeeMaps\DTOs\Responses\RouteMatrixEntryCollection;
use BeeDelivery\BeeMaps\Support\ValueObjects\Distance;
use BeeDelivery\BeeMaps\Support\ValueObjects\Duration;

/**
 * Matriz derivada de posicoes numa reta: dispensa HTTP e deixa a ordem esperada
 * obvia de calcular a mao. Guarda a ultima requisicao para o teste conferir a
 * lista de pontos que a estrategia montou.
 */
final class RouteMatrixFalso implements RouteMatrix
{
    public ?RouteMatrixRequest $ultimaRequisicao = null;

    /** @param list<int|float> $posicoes */
    public function __construct(private readonly array $posicoes)
    {
    }

    public function matrix(RouteMatrixRequest $request): RouteMatrixEntryCollection
    {
        $this->ultimaRequisicao = $request;

        $entradas = [];

        foreach ($this->posicoes as $o => $posOrigem) {
            foreach ($this->posicoes as $d => $posDestino) {
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
}
