<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateLicense;
use App\Services\Licensing\LicenseActivity;
use App\Services\Licensing\LicensePolicy;
use App\Services\Licensing\LicenseTokenIssuer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(
        private readonly LicensePolicy $policy,
        private readonly LicenseActivity $activity,
        private readonly LicenseTokenIssuer $tokens,
    ) {}

    /**
     * Verify the calling license and return a signed answer.
     *
     * Both positive and negative answers are signed, so a client can trust
     * "revoked" or "expired" as much as "valid".
     */
    public function verify(Request $request): JsonResponse
    {
        $input = $request->validate([
            'domain' => ['nullable', 'string', 'max:255'],
            'instance_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'app_version' => ['nullable', 'string', 'max:64'],
            'challenge' => ['nullable', 'string', 'min:8', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);

        $license = AuthenticateLicense::license($request);
        $now = now();
        $reason = $this->policy->denialReason($license, $request->ip(), $input['domain'] ?? null, $now);
        $isValid = $reason === null;

        $this->activity->recordVerification($license, $isValid, $request->ip(), $input['app_version'] ?? null);

        $offlineUntil = $now->copy();

        if ($isValid) {
            $offlineUntil = $now->copy()->addHours(max(0, (int) config('license.offline_grace_hours', 72)));
            $validUntil = $license->validUntil();

            if ($validUntil !== null && $validUntil->lt($offlineUntil)) {
                $offlineUntil = $validUntil;
            }
        }

        $licenseData = [
            'key_id' => $license->keyId,
            'customer' => $license->customerName,
            'product' => $license->productSlug,
            'max_users' => $license->maxUsers,
            'features' => (object) $license->features,
            'starts_at' => $license->startsAt,
            'expires_at' => $license->expiresAt,
            'days_remaining' => $license->validUntil() === null ? null : max(0, (int) $now->copy()->startOfDay()->diffInDays($license->validUntil()->copy()->startOfDay(), false)),
        ];

        $token = $this->tokens->issue([
            'iss' => (string) config('license.issuer'),
            'sub' => $license->keyId,
            'prd' => $license->productSlug,
            'valid' => $isValid,
            'reason' => $reason,
            'dom' => isset($input['domain']) ? LicensePolicy::normalizeHost($input['domain']) : null,
            'ins' => $input['instance_id'] ?? null,
            'chl' => $input['challenge'] ?? null,
            'lic' => $licenseData,
            'iat' => $now->getTimestamp(),
            'exp' => $offlineUntil->getTimestamp(),
        ]);

        return ApiResponse::success([
            'valid' => $isValid,
            'reason' => $reason,
            'message' => $isValid ? 'لایسنس معتبر است.' : LicensePolicy::MESSAGES[$reason],
            'license' => $licenseData,
            'offline_until' => $offlineUntil->toIso8601String(),
            'server_time' => $now->getTimestamp(),
            'token' => $token,
        ]);
    }
}
