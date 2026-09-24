<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\Support\CredentialRedaction;
use BeeDelivery\BeeMaps\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CredentialRedactionTest extends TestCase
{
    public static function texts(): array
    {
        return [
            'apiKey do HERE' => ['https://x/y?apiKey=SEGREDO&lang=pt', 'https://x/y?apiKey=[REDACTED]&lang=pt'],
            'key do Google' => ['falha em https://x/y?key=SEGREDO&a=1', 'falha em https://x/y?key=[REDACTED]&a=1'],
            'token no meio' => ['a?b=1&token=SEGREDO&c=2', 'a?b=1&token=[REDACTED]&c=2'],
            // O nome do parametro fica visivel de proposito: sem ele o log perde
            // a capacidade de diagnostico.
            'sem credencial' => ['https://x/y?lang=pt-BR', 'https://x/y?lang=pt-BR'],
            // `key` como fragmento de outra palavra nao pode ser redigido: a
            // fronteira ?/& e o que impede de estragar texto legitimo.
            'palavra parecida' => ['monkey=banana', 'monkey=banana'],
        ];
    }

    #[DataProvider('texts')]
    public function test_redige_credencial_de_query(string $entry, string $expected): void
    {
        $this->assertSame($expected, CredentialRedaction::redact($entry));
    }
}
