<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cheap bookkeeping for the verify hot path: one upsert per request for the
 * daily counter, and a "last seen" update at most every few minutes.
 */
class LicenseActivity
{
    public function recordVerification(LicenseSnapshot $license, bool $accepted, ?string $ip, ?string $appVersion): void
    {
        $column = $accepted ? 'verifications' : 'rejections';

        DB::table('license_daily_stats')->upsert(
            [[
                'license_id' => $license->id,
                'day' => now()->toDateString(),
                'verifications' => $accepted ? 1 : 0,
                'rejections' => $accepted ? 0 : 1,
            ]],
            ['license_id', 'day'],
            [$column => DB::raw("{$column} + 1")],
        );

        $this->touchLastSeen($license, $ip, $appVersion);
    }

    public function touchLastSeen(LicenseSnapshot $license, ?string $ip, ?string $appVersion): void
    {
        $throttleKey = 'license-last-seen:'.$license->id;
        $resolution = max(30, (int) config('license.last_seen_resolution_seconds', 300));

        if (! Cache::store(config('license.cache_store'))->add($throttleKey, true, $resolution)) {
            return;
        }

        // Direct query: saving the model would flush the license cache.
        DB::table('licenses')->where('id', $license->id)->update([
            'last_seen_at' => now(),
            'last_seen_ip' => $ip,
            'last_seen_version' => $appVersion === null ? null : mb_substr($appVersion, 0, 64),
        ]);
    }
}
