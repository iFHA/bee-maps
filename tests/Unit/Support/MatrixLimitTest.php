<?php

namespace BeeDelivery\BeeMaps\Tests\Unit\Support;

use BeeDelivery\BeeMaps\Support\MatrixLimit;
use BeeDelivery\BeeMaps\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MatrixLimitTest extends TestCase
{
    public static function values(): array
    {
        return [
            'ausente' => [null, null],
            // Variavel declarada sem valor no .env chega como string vazia, e
            // (int) '' e 0 — o que transformava a guarda em "recuse tudo".
            'string vazia' => ['', null],
            'zero string' => ['0', null],
            'zero int' => [0, null],
            'negativo' => [-5, null],
            'numero string' => ['625', 625],
            'numero int' => [625, 625],
        ];
    }

    #[DataProvider('values')]
    public function test_normalizes(mixed $entry, ?int $expected): void
    {
        $this->assertSame($expected, MatrixLimit::normalize($entry));
    }
}
