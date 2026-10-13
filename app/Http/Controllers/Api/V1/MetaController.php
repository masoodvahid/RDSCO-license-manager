<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Licensing\SigningKey;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Unauthenticated helpers for client setup and clock checks.
 */
class MetaController extends Controller
{
    public function ping(): JsonResponse
    {
        return ApiResponse::success([
            'server_time' => time(),
            'api_version' => 1,
        ]);
    }

    /**
     * Public key for verifying license tokens. Clients should pin this value
     * in their configuration instead of fetching it at runtime.
     */
    public function publicKey(SigningKey $signingKey): JsonResponse
    {
        return ApiResponse::success([
            'algorithm' => 'Ed25519',
            'key_id' => $signingKey->keyId(),
            'public_key' => $signingKey->publicKey(),
        ]);
    }
}
