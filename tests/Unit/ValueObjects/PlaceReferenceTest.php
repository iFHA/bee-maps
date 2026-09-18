<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\ValueObjects;

use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Exceptions\PlaceReferenceProviderMismatchException;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class PlaceReferenceTest extends TestCase
{
    public function test_aceita_referencia_do_mesmo_provider(): void
    {
        $ref = new PlaceReference(Provider::Here, 'here:pds:place:76-1234');

        $ref->assertBelongsTo(Provider::Here);

        $this->assertSame('here:pds:place:76-1234', $ref->id);
    }

    public function test_recusa_place_id_do_google_num_provider_here(): void
    {
        $ref = new PlaceReference(Provider::Google, 'ChIJ0WGkg4FEzpQRrlsz_whLqZs');

        $this->expectException(PlaceReferenceProviderMismatchException::class);

        $ref->assertBelongsTo(Provider::Here);
    }
}
