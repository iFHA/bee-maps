<?php

namespace BeeDelivery\BeeMaps\Support;

/**
 * Normaliza o limite de elementos da matriz vindo do config. Existe como classe
 * propria porque a regra e identica nos dois providers, e enquanto era copia as
 * duas guardas podiam divergir — divergencia que ja custou uma correcao.
 */
final class MatrixLimit
{
    /**
     * Ausente, vazio ou nao-positivo significa "sem guarda no cliente". Uma
     * variavel declarada sem valor no .env chega como '' (o default do env() so
     * vale quando a variavel nao existe), e (int) '' e 0.
     */
    public static function normalize(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value > 0 ? (int) $value : null;
    }
}
