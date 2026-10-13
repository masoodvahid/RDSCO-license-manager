<?php

namespace App\Http\Middleware;

use App\Services\Licensing\LicenseCache;
use App\Services\Licensing\LicensePolicy;
use App\Services\Licensing\LicenseSnapshot;
use App\Services\Licensing\NonceGuard;
use App\Services\Licensing\RequestSignature;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a signed API request.
 *
 * Usage: "license.auth" (signature only) or "license.auth:strict" (signature,
 * one-time nonce, and an active license allowed from this IP).
 */
class AuthenticateLicense
{
    public const ATTRIBUTE = 'license';

    public function __construct(
        private readonly LicenseCache $licenses,
        private readonly NonceGuard $nonces,
        private readonly LicensePolicy $policy,
    ) {}

    public function handle(Request $request, Closure $next, string $mode = 'signature'): Response
    {
        $keyId = $request->header('X-Key-Id');
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');
        $signature = $request->header('X-Signature');

        if (
            ! is_string($keyId) || preg_match('/^lk_[a-f0-9]{20}$/', $keyId) !== 1
            || ! is_string($timestamp) || preg_match('/^\d{10}$/', $timestamp) !== 1
            || ! is_string($nonce) || preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $nonce) !== 1
            || ! is_string($signature) || preg_match('/^[a-f0-9]{64}$/i', $signature) !== 1
        ) {
            return $this->reject($request, 'missing_credentials', 'هدرهای احراز هویت ناقص یا نامعتبر است.', 401);
        }

        $maxBodyBytes = (int) config('license.max_body_bytes', 65_536);

        if ((int) $request->server('CONTENT_LENGTH', 0) > $maxBodyBytes || strlen($request->getContent()) > $maxBodyBytes) {
            return ApiResponse::error('payload_too_large', 'حجم درخواست بیش از حد مجاز است.', 413);
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > max(30, (int) config('license.signature_tolerance_seconds', 300))) {
            return $this->reject($request, 'stale_request', 'زمان درخواست معتبر نیست. ساعت سرور برنامه را بررسی کنید.', 401, [
                'server_time' => now()->getTimestamp(),
            ]);
        }

        $license = $this->licenses->get($keyId);

        if ($license === null) {
            return $this->reject($request, 'invalid_credentials', 'کلید یا امضای درخواست معتبر نیست.', 401);
        }

        $body = in_array($request->method(), ['GET', 'HEAD'], true) ? '' : $request->getContent();

        if (! RequestSignature::verify($signature, $license->secret(), $request->method(), $request->getPathInfo(), $timestamp, $nonce, $body)) {
            return $this->reject($request, 'invalid_credentials', 'کلید یا امضای درخواست معتبر نیست.', 401, license: $license);
        }

        if ($mode === 'strict') {
            $ttl = 2 * max(30, (int) config('license.signature_tolerance_seconds', 300));

            if (! $this->nonces->claim($license->id, $nonce, $ttl)) {
                return $this->reject($request, 'replayed_request', 'این درخواست قبلاً ارسال شده است. برای هر درخواست Nonce جدید بسازید.', 409, license: $license);
            }

            $reason = $this->policy->denialReason($license, $request->ip());

            if ($reason !== null) {
                return ApiResponse::error($reason, LicensePolicy::MESSAGES[$reason], 403);
            }
        }

        $request->attributes->set(self::ATTRIBUTE, $license);

        return $next($request);
    }

    public static function license(Request $request): LicenseSnapshot
    {
        return $request->attributes->get(self::ATTRIBUTE);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function reject(Request $request, string $code, string $message, int $status, array $extra = [], ?LicenseSnapshot $license = null): Response
    {
        // Log rejections, but not more than a few per IP per minute.
        RateLimiter::attempt(
            'api-reject-log:'.$request->ip(),
            5,
            fn () => Log::warning('API request rejected.', [
                'code' => $code,
                'license' => $license?->keyId,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]),
            60,
        );

        return ApiResponse::error($code, $message, $status, $extra);
    }
}
