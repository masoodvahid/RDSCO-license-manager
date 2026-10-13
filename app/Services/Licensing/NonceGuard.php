<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\DB;

/**
 * Atomic replay protection backed by a primary key, so it works with any
 * cache driver (including files on shared hosting).
 */
class NonceGuard
{
    /**
     * Claim a nonce for a license. Returns false if it was already used.
     */
    public function claim(int $licenseId, string $nonce, int $ttlSeconds): bool
    {
        $now = time();

        $inserted = DB::table('api_nonces')->insertOrIgnore([
            'license_id' => $licenseId,
            'nonce_hash' => hash('sha256', $nonce),
            'expires_at' => $now + $ttlSeconds,
        ]);

        // Opportunistic cleanup so no cron job is required.
        if (random_int(1, 200) === 1) {
            $this->prune($now);
        }

        return $inserted === 1;
    }

    public function prune(?int $now = null): int
    {
        return DB::table('api_nonces')->where('expires_at', '<', $now ?? time())->delete();
    }
}
