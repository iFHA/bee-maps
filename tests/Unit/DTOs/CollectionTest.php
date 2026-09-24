<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\DTOs;

use BeeDelivery\BeeMaps\DTOs\Responses\Suggestion;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class CollectionTest extends TestCase
{
    public function test_an_empty_collection_is_not_an_error(): void
    {
        $collection = new SuggestionCollection();

        $this->assertTrue($collection->isEmpty());
        $this->assertCount(0, $collection);
        $this->assertNull($collection->first());
    }

    public function test_the_collection_is_iterable_and_preserves_order(): void
    {
        $collection = new SuggestionCollection(
            new Suggestion(new PlaceReference(Provider::Google, 'a'), 'Rua A', 'Rua A', 'Centro', false),
            new Suggestion(null, 'Postos Shell', 'Postos Shell', '', true),
        );

        $this->assertCount(2, $collection);
        $this->assertSame('Rua A', $collection->first()->description);

        $descriptions = [];
        foreach ($collection as $item) {
            $descriptions[] = $item->description;
        }

        $this->assertSame(['Rua A', 'Postos Shell'], $descriptions);
    }

    public function test_a_suggestion_without_a_resolvable_place_has_a_null_place(): void
    {
        $suggestion = new Suggestion(null, 'Postos Shell', 'Postos Shell', '', true);

        $this->assertNull($suggestion->place);
    }
}
