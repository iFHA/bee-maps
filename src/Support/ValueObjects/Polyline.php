<?php

namespace BeeDelivery\BeeMaps\Support\ValueObjects;

use BeeDelivery\BeeMaps\Contracts\PolylineDecoder;

/**
 * Unico dado do pacote que NAO e readonly: a decodificacao e preguicosa e
 * memoizada. Uma rota longa tem milhares de pontos, e o caso de uso mais
 * comum e repassar raw() para o front, que decodifica no cliente — decodificar
 * na construcao gastaria CPU em toda chamada para um valor que ninguem le.
 */
final class Polyline
{
    /** @var list<Coordinates>|null */
    private ?array $decoded = null;

    public function __construct(
        private readonly string $raw,
        private readonly PolylineDecoder $decoder,
    ) {
    }

    public function raw(): string
    {
        return $this->raw;
    }

    /**
     * @return list<Coordinates>
     */
    public function coordinates(): array
    {
        return $this->decoded ??= $this->decoder->decode($this->raw);
    }
}
