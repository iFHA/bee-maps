<?php

namespace BeeDelivery\BeeMaps\Support;

/**
 * Redige valores de parametros de query que parecem credenciais (ex.: apiKey,
 * token) em qualquer texto que possa conter a URL da requisicao, mantendo o nome
 * do parametro visivel para nao perder capacidade de diagnostico.
 *
 * Vive numa classe propria porque nao e so o cliente HTTP que monta mensagem com
 * texto cru de provider: mapper que traduz corpo de erro faz o mesmo, e a chave
 * vazaria para o log pelo caminho que nao redige.
 *
 * O Guzzle so redige a senha em `user:pass@host` (Psr7\Utils::redactUserInfo);
 * query strings com `apiKey=...` (HERE) ou `key=...` (Google) passam intactas.
 */
final class CredentialRedaction
{
    private const PARAMETROS = 'apiKey|api_key|key|token|access_token|signature|sig';

    public static function redigir(string $texto): string
    {
        return preg_replace(
            '/([?&])(' . self::PARAMETROS . ')=[^&\s]*/i',
            '$1$2=[REDACTED]',
            $texto,
        );
    }
}
