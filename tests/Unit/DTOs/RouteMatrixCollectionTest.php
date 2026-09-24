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
    private function entry(int $origin, int $destination, int $meters): RouteMatrixEntry
    {
        return new RouteMatrixEntry($origin, $destination, new Distance($meters), new Duration(60), true);
    }

    public function test_empty_collection(): void
    {
        $collection = new RouteMatrixEntryCollection();

        $this->assertTrue($collection->isEmpty());
        $this->assertNull($collection->first());
        $this->assertNull($collection->entry(0, 0));
    }

    public function test_an_entry_is_addressable_by_pair_even_out_of_order(): void
    {
        // Ordem literal que o Google devolveu numa matriz 2x2 real.
        $collection = new RouteMatrixEntryCollection(
            $this->entry(0, 1, 1807),
            $this->entry(0, 0, 974),
            $this->entry(1, 0, 1371),
            $this->entry(1, 1, 646),
        );

        $this->assertCount(4, $collection);
        $this->assertSame(974, $collection->entry(0, 0)->distance->meters);
        $this->assertSame(1807, $collection->entry(0, 1)->distance->meters);
        $this->assertSame(1371, $collection->entry(1, 0)->distance->meters);
        $this->assertSame(646, $collection->entry(1, 1)->distance->meters);
        $this->assertNull($collection->entry(9, 9));
    }

    public function test_the_request_counts_elements(): void
    {
        $request = new RouteMatrixRequest(
            [new Coordinates(-23.5, -46.6), new Coordinates(-23.6, -46.7)],
            [new Coordinates(-23.55, -46.65), new Coordinates(-23.58, -46.68), new Coordinates(-23.59, -46.69)],
        );

        $this->assertSame(6, $request->elements());
    }

    public function test_a_request_without_origin_or_destination_is_rejected(): void
    {
        $this->expectException(InvalidRequestException::class);

        new RouteMatrixRequest([], [new Coordinates(-23.5, -46.6)]);
    }

    public function test_the_oversized_matrix_exception_states_the_size_and_the_limit(): void
    {
        $exception = MatrixTooLargeException::make(Provider::Google, 900, 625);

        $this->assertInstanceOf(InvalidRequestException::class, $exception);
        $this->assertStringContainsString('900', $exception->getMessage());
        $this->assertStringContainsString('625', $exception->getMessage());
    }

    public function test_points_with_non_sequential_keys_are_reindexed(): void
    {
        $points = [
            new Coordinates(-23.5, -46.6),
            new Coordinates(-23.6, -46.7),
            new Coordinates(-23.7, -46.8),
        ];

        // array_filter preserva as chaves originais: [0, 2]. Sem reindexar, o
        // json_encode do payload vira objeto ({"0":...,"2":...}) em vez de array
        // e os dois providers respondem 400 com mensagem opaca — e os indices do
        // resultado deixam de casar com as chaves que o chamador enxerga.
        $filtered = array_filter($points, fn (Coordinates $p) => $p->latitude !== -23.6);

        $request = new RouteMatrixRequest($filtered, [new Coordinates(-23.55, -46.65)]);

        $this->assertSame([0, 1], array_keys($request->origins));
        $this->assertTrue(array_is_list($request->origins));
        $this->assertTrue(array_is_list($request->destinations));
        $this->assertSame(-23.7, $request->origins[1]->latitude);
    }
}
