<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\DTOs;

use BeeDelivery\BeeMaps\DTOs\Requests\OptimizeWaypointsRequest;
use BeeDelivery\BeeMaps\Enums\OptimizationObjective;
use BeeDelivery\BeeMaps\Enums\TravelMode;
use BeeDelivery\BeeMaps\Exceptions\InvalidRequestException;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class OptimizeWaypointsRequestTest extends TestCase
{
    private function point(float $offset): Coordinates
    {
        return new Coordinates(-23.5 - $offset, -46.6 - $offset);
    }

    public function test_destino_nulo_significa_tour_aberto(): void
    {
        $request = new OptimizeWaypointsRequest(
            origin: $this->point(0),
            intermediates: [$this->point(0.1), $this->point(0.2)],
        );

        $this->assertNull($request->destination);
        $this->assertSame(TravelMode::Drive, $request->mode);
        $this->assertSame(OptimizationObjective::MinTravelTime, $request->objective);
    }

    public function test_intermediarios_sao_reindexados(): void
    {
        // array_filter preserva chaves: sem reindexar, a lista vira objeto no
        // json_encode e os indices devolvidos em $order deixam de casar com o
        // que o chamador enxerga. Mesma regra do RouteMatrixRequest.
        $points = [0 => $this->point(0.1), 2 => $this->point(0.2), 5 => $this->point(0.3)];

        $request = new OptimizeWaypointsRequest(
            origin: $this->point(0),
            destination: $this->point(0.9),
            intermediates: $points,
        );

        $this->assertSame([0, 1, 2], array_keys($request->intermediates));
    }

    public function test_sem_intermediarios_nao_ha_ordem_para_devolver(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/intermediari/i');

        new OptimizeWaypointsRequest(origin: $this->point(0), destination: $this->point(0.9));
    }

    public function test_um_intermediario_so_nao_e_erro(): void
    {
        // A ordem e trivialmente [0], mas os totais nao sao: o chamador ainda
        // quer saber quanto custa origem -> parada -> destino. Recusar quebraria
        // quem itera sobre pedidos e as vezes encontra um de uma parada so.
        $request = new OptimizeWaypointsRequest(
            origin: $this->point(0),
            destination: $this->point(0.9),
            intermediates: [$this->point(0.1)],
        );

        $this->assertCount(1, $request->intermediates);
    }
}
