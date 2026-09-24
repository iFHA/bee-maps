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
    public ?RouteMatrixRequest $lastRequest = null;

    /** @param list<int|float> $positions */
    public function __construct(private readonly array $positions)
    {
    }

    public function matrix(RouteMatrixRequest $request): RouteMatrixEntryCollection
    {
        $this->lastRequest = $request;

        $entries = [];

        foreach ($this->positions as $o => $originPos) {
            foreach ($this->positions as $d => $destinationPos) {
                $meters = (int) abs($originPos - $destinationPos);

                $entries[] = new RouteMatrixEntry(
                    originIndex: $o,
                    destinationIndex: $d,
                    distance: new Distance($meters),
                    duration: new Duration($meters * 6),
                    reachable: true,
                );
            }
        }

        return new RouteMatrixEntryCollection(...$entries);
    }
}
