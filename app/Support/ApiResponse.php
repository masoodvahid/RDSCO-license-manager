<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * The single response envelope of the public API.
 *
 *   success: {"ok": true,  "data": {...}}
 *   failure: {"ok": false, "error": {"code": "...", "message": "...", ...}}
 *
 * "code" is stable and meant for programs; "message" is for humans (Persian).
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public static function success(array $data, int $status = 200, array $headers = []): JsonResponse
    {
        return self::json(['ok' => true, 'data' => $data], $status, $headers);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    public static function error(string $code, string $message, int $status, array $extra = [], array $headers = []): JsonResponse
    {
        return self::json([
            'ok' => false,
            'error' => ['code' => $code, 'message' => $message, ...$extra],
        ], $status, $headers);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private static function json(array $payload, int $status, array $headers): JsonResponse
    {
        return new JsonResponse($payload, $status, [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            ...$headers,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
