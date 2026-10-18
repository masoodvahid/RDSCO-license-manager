@php
    $code = fn (string $value) => '<pre class="mt-3 overflow-x-auto rounded-lg bg-gray-950 p-4 text-left text-xs leading-6 text-gray-100" dir="ltr">'.e($value).'</pre>';
    $errorCodes = [
        ['missing_credentials', 401, 'هدرهای احراز هویت ناقص یا نامعتبر است.'],
        ['invalid_credentials', 401, 'کلید ناشناخته است یا امضا با Secret مطابقت ندارد.'],
        ['stale_request', 401, 'اختلاف X-Timestamp با ساعت سرور بیش از ۵ دقیقه است.'],
        ['replayed_request', 409, 'این Nonce قبلاً استفاده شده است (فقط مسیرهای پولی و ثبت خطا).'],
        ['license_suspended / license_revoked / expired / not_started', 403, 'لایسنس قابل استفاده نیست (در مسیرهای پولی و ثبت خطا).'],
        ['customer_suspended / product_inactive / ip_not_allowed', 403, 'مشتری یا محصول غیرفعال است یا IP مجاز نیست.'],
        ['service_not_allowed', 403, 'سرویس برای این لایسنس فعال نیست.'],
        ['validation_failed', 422, 'داده‌ها معتبر نیست؛ جزئیات در error.fields.'],
        ['payload_too_large', 413, 'بدنه درخواست بیش از ۶۴ کیلوبایت است.'],
        ['rate_limited', 429, 'بیش از سقف درخواست؛ هدر Retry-After را رعایت کنید.'],
        ['shahkar_*', '502 / 503 / 504', 'شاهکار یا سیتاد پاسخ قطعی نداد؛ error.retryable را ببینید. هزینه‌ای ثبت نمی‌شود.'],
        ['shahkar_circuit_open', 503, 'سیتاد چند بار پشت سر هم پاسخ نداده و استعلام‌ها موقتاً (حدود ۳۰ ثانیه) بدون انتظار رد می‌شوند. کمی بعد دوباره تلاش کنید.'],
        ['maintenance', 503, 'سامانه در حال بروزرسانی است.'],
    ];
@endphp

<div>
    <x-page-header title="مستندات API" description="برای برنامه‌های لاراولی از SDK آماده (sdk/laravel) استفاده کنید؛ این صفحه برای پیاده‌سازی در زبان‌های دیگر است." />

    <div class="space-y-6 text-sm leading-7 text-gray-700">
        <x-card title="اصول کلی">
            <ul class="list-disc space-y-1 pr-5">
                <li>آدرس پایه: <span class="ltr-value">{{ $baseUrl }}/api/v1</span></li>
                <li>همه پاسخ‌ها JSON با یک قالب ثابت‌اند: <span class="ltr-value">{"ok": true, "data": {...}}</span> یا <span class="ltr-value">{"ok": false, "error": {"code": "...", "message": "..."}}</span></li>
                <li>مقدار <span class="ltr-value">error.code</span> ثابت و برای برنامه است؛ <span class="ltr-value">error.message</span> فارسی و برای نمایش است.</li>
                <li>سقف درخواست به ازای هر لایسنس در دقیقه: بررسی لایسنس {{ config('license.rate_limits.verify') }}، شاهکار {{ config('license.rate_limits.shahkar') }}، ثبت خطا {{ config('license.rate_limits.errors') }}.</li>
            </ul>
        </x-card>

        <x-card title="احراز هویت و امضای درخواست">
            <p>هر درخواست این هدرها را دارد. خود Secret هرگز ارسال نمی‌شود.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>هدر</th><th>مقدار</th></tr></thead>
                    <tbody>
                        <tr><td class="ltr-value">X-Key-Id</td><td>شناسه کلید لایسنس (lk_...)</td></tr>
                        <tr><td class="ltr-value">X-Timestamp</td><td>زمان Unix به ثانیه (حداکثر ۵ دقیقه اختلاف با سرور)</td></tr>
                        <tr><td class="ltr-value">X-Nonce</td><td>رشته تصادفی یکتا، ۱۶ تا ۱۲۸ نویسه از <span class="ltr-value">[A-Za-z0-9._:-]</span></td></tr>
                        <tr><td class="ltr-value">X-Signature</td><td>HMAC-SHA256 رشته استاندارد با Secret، به صورت hex</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-4">رشته استاندارد (خط‌ها با <span class="ltr-value">\n</span> جدا می‌شوند؛ مسیر بدون نام دامنه):</p>
            {!! $code("POST\n/api/v1/license/verify\n1760000000\n9f1c2e7a5b3d4c6e8f0a1b2c3d4e5f60\n<sha256 hex of the raw request body>") !!}
            <p class="mt-4">نمونه PHP بدون SDK:</p>
            {!! $code(<<<'PHP'
$body = json_encode(['domain' => 'https://crm.example.com', 'challenge' => bin2hex(random_bytes(16))]);
$timestamp = (string) time();
$nonce = bin2hex(random_bytes(16));
$canonical = implode("\n", ['POST', '/api/v1/license/verify', $timestamp, $nonce, hash('sha256', $body)]);

$headers = [
    'Content-Type: application/json',
    'X-Key-Id: '.$keyId,
    'X-Timestamp: '.$timestamp,
    'X-Nonce: '.$nonce,
    'X-Signature: '.hash_hmac('sha256', $canonical, $secret),
];
PHP) !!}
        </x-card>

        <x-card title="POST /api/v1/license/verify — بررسی لایسنس">
            <p>همه فیلدها اختیاری‌اند. <span class="ltr-value">challenge</span> را تصادفی بسازید و در توکن برگشتی بررسی کنید تا پاسخ قدیمی قابل تکرار نباشد.</p>
            {!! $code('{"domain": "https://crm.example.com", "instance_id": "server-1", "app_version": "2.4.0", "challenge": "9f1c2e7a5b3d4c6e"}') !!}
            <p class="mt-4">پاسخ (همیشه HTTP 200 برای کلید معتبر؛ اعتبار در <span class="ltr-value">valid</span> است):</p>
            {!! $code(<<<'JSON'
{
  "ok": true,
  "data": {
    "valid": true,
    "reason": null,
    "message": "لایسنس معتبر است.",
    "license": {"key_id": "lk_...", "customer": "...", "product": "rahkar-crm", "max_users": 10,
                "features": {"reports": true}, "starts_at": "2026-03-21", "expires_at": "2027-03-20", "days_remaining": 158},
    "offline_until": "2026-10-16T12:00:00+03:30",
    "server_time": 1760000000,
    "token": "rdl1.eyJ....sig"
  }
}
JSON) !!}
            <p class="mt-4">مقادیر <span class="ltr-value">reason</span>: <span class="ltr-value">expired, not_started, license_suspended, license_revoked, customer_suspended, product_inactive, ip_not_allowed, domain_not_allowed</span>.</p>
        </x-card>

        <x-card title="توکن امضاشده و کار آفلاین">
            <p>قالب توکن: <span class="ltr-value">rdl1.&lt;base64url(JSON)&gt;.&lt;base64url(Ed25519 signature)&gt;</span>. امضا روی <span class="ltr-value">rdl1.&lt;payload&gt;</span> است. کلید عمومی را در برنامه ثابت (pin) کنید:</p>
            <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2"><x-copy :value="$publicKey" class="text-xs" /></div>
            {!! $code(<<<'PHP'
[$prefix, $payload, $signature] = explode('.', $token);
$valid = sodium_crypto_sign_verify_detached(
    base64_decode(strtr($signature, '-_', '+/')),
    $prefix.'.'.$payload,
    base64_decode($publicKey),
);
$claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
// $claims: valid, reason, sub (key id), chl (challenge), lic (license), iat, exp
PHP) !!}
            <p class="mt-4">برنامه تا زمان <span class="ltr-value">exp</span> آخرین توکن معتبر می‌تواند بدون اتصال کار کند (اکنون {{ $graceHours }} ساعت، و نه بیشتر از پایان لایسنس). در پاسخ منفی <span class="ltr-value">exp = iat</span> است.</p>
        </x-card>

        <x-card title="POST /api/v1/shahkar/verify — استعلام شاهکار">
            <p>Nonce هر درخواست باید یکتا باشد. <span class="ltr-value">reference</span> اختیاری است؛ ارسال دوباره با همان مقدار تا ۲۴ ساعت پاسخ قبلی را بدون هزینه جدید برمی‌گرداند.</p>
            {!! $code('{"national_code": "0499370899", "mobile": "09123456789", "reference": "signup-1234"}') !!}
            {!! $code('{"ok": true, "data": {"matched": true, "code": 200, "message": "...", "tracking_id": "050120260101...", "reference": "signup-1234", "charged": true, "replayed": false}}') !!}
            <p class="mt-4">فقط پاسخ‌های قطعی (<span class="ltr-value">matched</span> برابر true یا false) هزینه دارند.</p>
        </x-card>

        <x-card title="POST /api/v1/errors — ثبت خطای برنامه">
            {!! $code('{"message": "SQLSTATE[HY000] ...", "level": "error", "exception_class": "PDOException", "file": "/app/x.php", "line": 42, "trace": "...", "context": {"order_id": 7}, "url": "https://...", "environment": "production", "app_version": "2.4.0"}') !!}
            <p class="mt-2">فقط <span class="ltr-value">message</span> الزامی است. خطاهای یکسان (کلاس، فایل، خط و شکل پیام) گروه‌بندی و شمرده می‌شوند. کلیدهای حساس در context پوشانده می‌شوند.</p>
        </x-card>

        <x-card title="مسیرهای عمومی">
            <ul class="list-disc space-y-1 pr-5">
                <li><span class="ltr-value">GET /api/v1/ping</span> — زمان سرور (برای بررسی اختلاف ساعت)</li>
                <li><span class="ltr-value">GET /api/v1/public-key</span> — کلید عمومی امضا (برای تنظیم اولیه؛ در برنامه pin شود)</li>
            </ul>
        </x-card>

        <x-card title="کدهای خطای API" :padding="false">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>code</th><th>HTTP</th><th>توضیح</th></tr></thead>
                    <tbody>
                        @foreach ($errorCodes as [$errorCode, $status, $description])
                            <tr><td class="ltr-value">{{ $errorCode }}</td><td>{{ $status }}</td><td class="whitespace-normal">{{ $description }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="کدهای پاسخ شاهکار" :padding="false">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>کد</th><th>پیام</th><th>نتیجه</th><th>شارژ</th><th>تلاش مجدد خودکار</th></tr></thead>
                    <tbody>
                        @foreach ($shahkarCodes as $shahkarCode => $response)
                            <tr>
                                <td class="ltr-value">{{ $shahkarCode }}</td>
                                <td class="whitespace-normal">{{ $response['message'] }}</td>
                                <td>{{ $response['matched'] === true ? 'تطابق' : ($response['matched'] === false ? 'عدم تطابق' : 'خطا') }}</td>
                                <td>{{ $response['matched'] !== null ? 'بله' : 'خیر' }}</td>
                                <td>{{ $response['retryable'] ? 'بله' : 'خیر' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</div>
