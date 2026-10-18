<div dir="rtl">

# کلاینت لاراول سامانه لایسنس (rdsco/license-client)

این پکیج نرم‌افزارهای لاراولی شما را به سامانه مدیریت لایسنس وصل می‌کند:

- **بررسی لایسنس** با پاسخ امضاشده (Ed25519) و کش آفلاین
- **استعلام شاهکار** از طریق سامانه، با شارژ خودکار حساب مشتری
- **ارسال خودکار خطاها** (Exceptionهای گزارش‌نشده) به بخش «خطاهای سامانه‌ها»

## نصب

این پکیج داخل پوشه `sdk/laravel` مخزن سامانه است. یکی از دو روش:

**روش ۱ (پیشنهادی): مخزن جدا**

محتوای `sdk/laravel` را در یک مخزن جدا (مثلاً `masoodvahid/rdsco-license-client`) قرار دهید و یک تگ مثل `v1.0.0` بسازید. سپس در `composer.json` برنامه:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/masoodvahid/rdsco-license-client" }
],
"require": {
    "rdsco/license-client": "^1.0"
}
```

**روش ۲: کپی در پروژه**

پوشه را در `packages/rdsco-license-client` برنامه کپی کنید:

```json
"repositories": [
    { "type": "path", "url": "packages/rdsco-license-client" }
]
```

```bash
composer require rdsco/license-client:@dev
```

## تنظیمات

مقادیر را از صفحه همان لایسنس در پنل کپی کنید:

```env
RDSCO_LICENSE_URL=https://api.rahkar-digital.ir
RDSCO_LICENSE_KEY_ID=lk_xxxxxxxxxxxxxxxxxxxx
RDSCO_LICENSE_SECRET=sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
RDSCO_LICENSE_PUBLIC_KEY=base64...
RDSCO_LICENSE_APP_VERSION=1.0.0
```

برای تغییر تنظیمات پیشرفته:

```bash
php artisan vendor:publish --tag=rdsco-license-config
```

| تنظیم | پیش‌فرض | توضیح |
|---|---|---|
| `RDSCO_LICENSE_DOMAIN` | `APP_URL` | دامنه‌ای که به سرور اعلام می‌شود |
| `RDSCO_LICENSE_CHECK_INTERVAL` | `60` | فاصله بررسی آنلاین (دقیقه) |
| `RDSCO_LICENSE_CACHE_STORE` | پیش‌فرض برنامه | محل نگهداری توکن؛ باید ماندگار باشد |
| `RDSCO_LICENSE_REPORT_ERRORS` | `true` | ارسال خودکار خطاها |

## بررسی لایسنس

### محافظت از مسیرها

```php
Route::middleware(['auth', 'rdsco.licensed'])->group(function () {
    // ...
});

// فقط اگر قابلیت reports در لایسنس فعال باشد
Route::get('/reports', ReportController::class)->middleware('rdsco.licensed:reports');
```

### در کد

```php
use Rdsco\LicenseClient\Facades\RdscoLicense;

$status = RdscoLicense::status();

if (! $status->isValid()) {
    abort(403, $status->message());
}

$status->feature('branches', 1);   // مقدار قابلیت
$status->hasFeature('reports');    // true/false
$status->maxUsers();               // حداکثر کاربر یا null
$status->daysRemaining();          // روزهای باقی‌مانده تا انقضا
$status->code();                   // کد ۶ رقمی لایسنس
$status->appUrl();                 // آدرس اجرای سامانه که در پنل ثبت شده
```

برای اعمال سقف کاربر:

```php
$max = RdscoLicense::status()->maxUsers();

if ($max !== null && User::count() >= $max) {
    // اجازه ساخت کاربر جدید نده
}
```

### رفتار در قطعی ارتباط

- هر `RDSCO_LICENSE_CHECK_INTERVAL` دقیقه یک بار با سرور بررسی می‌شود؛ بین دو بررسی، توکن امضاشده از کش خوانده می‌شود.
- اگر سرور در دسترس نباشد، برنامه تا پایان «مهلت آفلاین» آخرین پاسخ معتبر (پیش‌فرض ۷۲ ساعت، و نه بیشتر از تاریخ انقضای لایسنس) کار می‌کند.
- پاسخ منفی (باطل، تعلیق، منقضی) با قطعی سرور به پاسخ مثبت تبدیل نمی‌شود.
- توکن با کلید عمومی تنظیم‌شده بررسی می‌شود؛ ویرایش فایل کش یا یک سرور جعلی نمی‌تواند لایسنس را فعال کند.

### دستور خط فرمان

```bash
php artisan rdsco:license            # وضعیت فعلی
php artisan rdsco:license --refresh  # بررسی آنلاین
```

## استعلام شاهکار

```php
use Rdsco\LicenseClient\Exceptions\LicenseServerException;
use Rdsco\LicenseClient\ShahkarClient;

try {
    $result = app(ShahkarClient::class)->verify(
        nationalCode: '0499370899',
        mobile: '09123456789',
        reference: 'signup-'.$user->id, // اختیاری؛ برای تکرار امن درخواست
    );

    if ($result['matched']) {
        // شماره همراه متعلق به صاحب کد ملی است
    } else {
        // عدم تطابق: $result['message']
    }
} catch (LicenseServerException $exception) {
    // شاهکار پاسخ قطعی نداد (هزینه‌ای ثبت نمی‌شود)
    // $exception->errorCode, $exception->getMessage(), $exception->retryable
}
```

- فقط پاسخ قطعی (تطابق یا عدم تطابق) هزینه دارد.
- اگر درخواست به‌خاطر Timeout قطع شد، همان درخواست را با همان `reference` دوباره بفرستید؛ تا ۲۴ ساعت پاسخ قبلی بدون هزینه جدید برگردانده می‌شود.

## گزارش خطا

با `RDSCO_LICENSE_REPORT_ERRORS=true` (پیش‌فرض)، هر Exception که لاراول گزارش می‌کند بعد از ارسال پاسخ به کاربر به سامانه فرستاده می‌شود. خطاهای یکسان حداکثر هر ۶۰ ثانیه یک بار ارسال می‌شوند و کلیدهای حساس در context (رمز، توکن، کد ملی، موبایل و…) در سرور پوشانده می‌شوند.

گزارش دستی:

```php
app(\Rdsco\LicenseClient\ErrorReporter::class)->report($exception, ['order_id' => $order->id], 'critical');
```

</div>
