<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\DTOs;

use BeeDelivery\BeeMaps\DTOs\Responses\Place;
use BeeDelivery\BeeMaps\DTOs\Responses\PlaceCollection;
use BeeDelivery\BeeMaps\Support\ValueObjects\Address;
use BeeDelivery\BeeMaps\Support\ValueObjects\Coordinates;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class PlaceCollectionTest extends TestCase
{
    public function test_colecao_vazia_e_o_caso_de_sem_resultado(): void
    {
        $collection = new PlaceCollection();

        $this->assertTrue($collection->isEmpty());
        $this->assertCount(0, $collection);
        $this->assertNull($collection->first());
        $this->assertSame([], $collection->all());
    }

    public function test_colecao_itera_e_devolve_o_primeiro(): void
    {
        $place = new Place(
            place: null,
            name: 'Farmacia Central',
            address: new Address(null, null, null, 'Sao Paulo', 'SP', 'Brasil', null, 'Sao Paulo - SP'),
            coordinates: new Coordinates(-23.5, -46.6),
        );

        $collection = new PlaceCollection($place, $place);

        $this->assertCount(2, $collection);
        $this->assertSame($place, $collection->first());
        $this->assertSame('Farmacia Central', iterator_to_array($collection)[1]->name);
    }
}
