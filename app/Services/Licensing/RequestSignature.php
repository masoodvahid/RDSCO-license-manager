<?php

namespace App\Services\Licensing;

/**
 * HMAC-SHA256 request signatures.
 *
 * Canonical string (lines joined with "\n"):
 *
 *   METHOD          upper-case HTTP method
 *   PATH            request path starting with "/", e.g. /api/v1/license/verify
 *   TIMESTAMP       unix seconds, same value as the X-Timestamp header
 *   NONCE           same value as the X-Nonce header
 *   BODY_SHA256     lower-case hex SHA-256 of the raw request body ("" for GET)
 *
 * X-Signature = lower-case hex HMAC-SHA256(canonical string, license secret)
 */
final class RequestSignature
{
    public static function canonical(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [
            strtoupper($method),
            '/'.ltrim($path, '/'),
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);
    }

    public static function sign(string $secret, string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return hash_hmac('sha256', self::canonical($method, $path, $timestamp, $nonce, $body), $secret);
    }

    public static function verify(string $signature, string $secret, string $method, string $path, string $timestamp, string $nonce, string $body): bool
    {
        return hash_equals(self::sign($secret, $method, $path, $timestamp, $nonce, $body), strtolower($signature));
    }
}
