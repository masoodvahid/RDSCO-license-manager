<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Services\Licensing\LicenseTokenIssuer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Employer lookup for the products' mobile apps.
 *
 * One app is published for all customers. On first launch the employee types
 * the license's 6-digit code (the "employer code"); this endpoint answers
 * with the employer's name and the API address of their own install, signed with the server key so a
 * tampered answer cannot point the app (and employees' passwords) elsewhere.
 *
 * The endpoint is public. Wrong codes are counted per IP and the IP is
 * locked out for a while after a few of them, which makes guessing one of the
 * 900,000 codes impractical. Successful lookups do not count, because a whole
 * office behind one NAT address may set up the app on the same morning.
 */
class AppDirectoryController extends Controller
{
    public const TOKEN_TYPE = 'app_directory';

    /** Path of the product API under the customer's app address. */
    public const API_PATH = '/api/v1';

    public function __construct(
        private readonly LicenseTokenIssuer $tokens,
    ) {}

    public function resolve(Request $request): JsonResponse
    {
        $limits = $this->failureLimits($request);

        foreach ($limits as $key => [$maxAttempts]) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $retryAfter = RateLimiter::availableIn($key);

                return ApiResponse::error('rate_limited', 'تعداد تلاش‌های نادرست زیاد بوده است. کمی بعد دوباره امتحان کنید.', 429, ['retry_after' => $retryAfter], ['Retry-After' => (string) $retryAfter]);
            }
        }

        $request->merge(['code' => self::normalizeDigits((string) $request->input('code'))]);

        $input = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'product' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            // Random per request; echoed in the token so an old answer cannot be replayed.
            'challenge' => ['nullable', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ], [
            'code.regex' => 'کد کارفرما باید ۶ رقم باشد.',
        ]);

        $license = License::query()
            ->with(['customer', 'product'])
            ->where('code', $input['code'])
            ->whereNotNull('app_url')
            ->whereHas('product', fn ($product) => $product->where('slug', $input['product']))
            ->first();

        if ($license === null) {
            foreach ($limits as $key => [, $decaySeconds]) {
                RateLimiter::hit($key, $decaySeconds);
            }

            return ApiResponse::error('code_not_found', 'کد کارفرما معتبر نیست.', 404);
        }

        if (! $this->usable($license)) {
            return ApiResponse::error('employer_inactive', 'سرویس این کارفرما در حال حاضر فعال نیست. با واحد منابع انسانی تماس بگیرید.', 403);
        }

        $appUrl = self::appRoot((string) $license->appUrl());

        // Addresses saved before the form required https cannot be used by the app.
        if (! str_starts_with($appUrl, 'https://')) {
            return ApiResponse::error('employer_misconfigured', 'آدرس سامانه این کارفرما برای اپ موبایل تنظیم نشده است. با پشتیبانی تماس بگیرید.', 409);
        }

        $employer = [
            'name' => (string) $license->customer->display_name,
            'app_url' => $appUrl,
            'api_base_url' => $appUrl.self::API_PATH,
        ];

        return ApiResponse::success([
            'code' => $input['code'],
            'product' => $license->product->slug,
            'employer' => $employer,
            // The app trusts only what is inside the signed token.
            'token' => $this->tokens->issue([
                'typ' => self::TOKEN_TYPE,
                'code' => $input['code'],
                'prd' => $license->product->slug,
                'name' => $employer['name'],
                'url' => $employer['api_base_url'],
                'chl' => $input['challenge'] ?? null,
                'iat' => time(),
            ]),
        ]);
    }

    /**
     * The address without a trailing "/api" or "/api/vN" someone may have
     * saved, so the API path is never doubled.
     */
    private static function appRoot(string $url): string
    {
        return rtrim((string) preg_replace('#/api(/v\d+)?/?$#i', '', rtrim($url, '/')), '/');
    }

    /**
     * @return array<string, array{int, int}> key => [max attempts, decay seconds]
     */
    private function failureLimits(Request $request): array
    {
        $ip = (string) $request->ip();

        return [
            'app-code-m:'.$ip => [max(1, (int) config('license.rate_limits.app_code_failures_per_minute', 10)), 60],
            'app-code-h:'.$ip => [max(1, (int) config('license.rate_limits.app_code_failures_per_hour', 30)), 3600],
        ];
    }

    private function usable(License $license): bool
    {
        return $license->status === LicenseStatus::Active
            && $license->customer?->isActive() === true
            && $license->product?->is_active === true
            && ($license->starts_at === null || $license->starts_at->lte(today()))
            && ! $license->isExpired();
    }

    /**
     * Persian and Arabic-Indic digits typed on phone keyboards → ASCII.
     */
    private static function normalizeDigits(string $value): string
    {
        return strtr(trim($value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
