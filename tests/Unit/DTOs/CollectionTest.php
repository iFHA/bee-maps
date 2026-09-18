<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\DTOs;

use BeeDelivery\BeeMaps\DTOs\Responses\Suggestion;
use BeeDelivery\BeeMaps\DTOs\Responses\SuggestionCollection;
use BeeDelivery\BeeMaps\Enums\Provider;
use BeeDelivery\BeeMaps\Support\ValueObjects\PlaceReference;
use BeeDelivery\BeeMaps\Tests\TestCase;

final class CollectionTest extends TestCase
{
    public function test_colecao_vazia_nao_e_erro(): void
    {
        $colecao = new SuggestionCollection();

        $this->assertTrue($colecao->isEmpty());
        $this->assertCount(0, $colecao);
        $this->assertNull($colecao->first());
    }

    public function test_colecao_e_iteravel_e_preserva_a_ordem(): void
    {
        $colecao = new SuggestionCollection(
            new Suggestion(new PlaceReference(Provider::Google, 'a'), 'Rua A', 'Rua A', 'Centro', false),
            new Suggestion(null, 'Postos Shell', 'Postos Shell', '', true),
        );

        $this->assertCount(2, $colecao);
        $this->assertSame('Rua A', $colecao->first()->description);

        $descricoes = [];
        foreach ($colecao as $item) {
            $descricoes[] = $item->description;
        }

        $this->assertSame(['Rua A', 'Postos Shell'], $descricoes);
    }

    public function test_sugestao_sem_lugar_resolvivel_tem_place_nulo(): void
    {
        $sugestao = new Suggestion(null, 'Postos Shell', 'Postos Shell', '', true);

        $this->assertNull($sugestao->place);
    }
}
