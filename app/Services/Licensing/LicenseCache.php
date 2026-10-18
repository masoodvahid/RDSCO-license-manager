<?php

namespace App\Services\Licensing;

use App\Models\License;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache of license snapshots keyed by public key id.
 *
 * Unknown key ids are cached too (as a miss) so random keys cannot be used
 * to hammer the database.
 */
class LicenseCache
{
    private const MISSING = '__missing__';

    public function get(string $keyId): ?LicenseSnapshot
    {
        $cached = $this->store()->remember(
            $this->cacheKey($keyId),
            max(10, (int) config('license.cache_seconds', 300)),
            function () use ($keyId): LicenseSnapshot|string {
                $license = License::query()
                    ->with(['customer', 'product', 'services'])
                    ->where('key_id', $keyId)
                    ->first();

                return $license === null ? self::MISSING : LicenseSnapshot::fromModel($license);
            },
        );

        return $cached instanceof LicenseSnapshot ? $cached : null;
    }

    public function forget(string $keyId): void
    {
        $this->store()->forget($this->cacheKey($keyId));
    }

    private function cacheKey(string $keyId): string
    {
        return 'license:v2:'.hash('sha256', $keyId);
    }

    private function store(): Repository
    {
        return Cache::store(config('license.cache_store'));
    }
}
