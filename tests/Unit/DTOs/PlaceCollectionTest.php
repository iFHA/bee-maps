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
        $colecao = new PlaceCollection();

        $this->assertTrue($colecao->isEmpty());
        $this->assertCount(0, $colecao);
        $this->assertNull($colecao->first());
        $this->assertSame([], $colecao->all());
    }

    public function test_colecao_itera_e_devolve_o_primeiro(): void
    {
        $lugar = new Place(
            place: null,
            name: 'Farmacia Central',
            address: new Address(null, null, null, 'Sao Paulo', 'SP', 'Brasil', null, 'Sao Paulo - SP'),
            coordinates: new Coordinates(-23.5, -46.6),
        );

        $colecao = new PlaceCollection($lugar, $lugar);

        $this->assertCount(2, $colecao);
        $this->assertSame($lugar, $colecao->first());
        $this->assertSame('Farmacia Central', iterator_to_array($colecao)[1]->name);
    }
}
