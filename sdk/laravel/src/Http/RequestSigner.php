<?php

namespace Rdsco\LicenseClient\Http;

/**
 * HMAC-SHA256 signature of a request (must match the server's RequestSignature).
 *
 * Canonical string: METHOD \n PATH \n TIMESTAMP \n NONCE \n sha256(BODY)
 */
final class RequestSigner
{
    public static function sign(string $secret, string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        $canonical = implode("\n", [
            strtoupper($method),
            '/'.ltrim($path, '/'),
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);

        return hash_hmac('sha256', $canonical, $secret);
    }

    public static function nonce(): string
    {
        return bin2hex(random_bytes(16));
    }
}
