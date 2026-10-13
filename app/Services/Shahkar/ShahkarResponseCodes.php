<?php

namespace App\Services\Shahkar;

class ShahkarResponseCodes
{
    /**
     * @var array<int, array{message: string, category: string, matched: bool|null, retryable: bool, http_status: int}>
     */
    private const RESPONSES = [
        200 => ['message' => 'درخواست موفق است و تطابق برقرار است.', 'category' => 'matched', 'matched' => true, 'retryable' => false, 'http_status' => 200],
        211 => ['message' => 'بخشی از اطلاعات هویتی با مرجع تطبیق مشابهت ندارد.', 'category' => 'mismatch', 'matched' => false, 'retryable' => false, 'http_status' => 200],
        300 => ['message' => 'سرویس‌دهنده مجاز احراز هویت نشده است.', 'category' => 'upstream_authentication', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        301 => ['message' => 'اجازه ثبت این سرویس وجود ندارد.', 'category' => 'upstream_authorization', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        302 => ['message' => 'سرویس موردنظر قابل پشتیبانی نیست.', 'category' => 'unsupported_service', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        303 => ['message' => 'نام کاربری به عنوان سرویس‌دهنده شناخته نشده است.', 'category' => 'upstream_authentication', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        310 => ['message' => 'قالب داده ارسالی به شاهکار صحیح نیست.', 'category' => 'upstream_validation', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        311 => ['message' => 'مقدار ارسال‌شده به شاهکار نامعتبر است.', 'category' => 'upstream_validation', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        312 => ['message' => 'شناسه درخواست شاهکار تکراری است.', 'category' => 'duplicate_request', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        313 => ['message' => 'سرویس قبلاً تخصیص داده شده است.', 'category' => 'business_conflict', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        314 => ['message' => 'خطا در فراخوانی ثبت سرویس WiFi/Mobile رخ داده است.', 'category' => 'upstream_error', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        316 => ['message' => 'کلاسه ارسالی با اپراتور تطابق ندارد.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        317 => ['message' => 'شماره مشخصه سرویس در پایگاه داده موجود نیست.', 'category' => 'not_found', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        318 => ['message' => 'برای شناسه هویتی بیش از سقف مجاز درخواست ارسال شده است.', 'category' => 'rate_limited', 'matched' => null, 'retryable' => false, 'http_status' => 429],
        319 => ['message' => 'کانال شاهکار موقتاً مسدود است.', 'category' => 'temporary_block', 'matched' => null, 'retryable' => false, 'http_status' => 503],
        320 => ['message' => 'شماره کلاسه در پایگاه داده موجود نیست.', 'category' => 'not_found', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        321 => ['message' => 'عملیات به علت حذف، بسته شدن یا انتقال کلاسه ممکن نیست.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        322 => ['message' => 'زمان مجاز حذف گذشته است.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        323 => ['message' => 'بیش از یک ماه از ثبت درخواست گذشته است.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        324 => ['message' => 'انتقال سرویس به خود مجاز نیست.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        325 => ['message' => 'شناسه درخواست شاهکار معتبر نیست.', 'category' => 'upstream_validation', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        326 => ['message' => 'درخواست خارج از بازه زمانی مجاز است.', 'category' => 'upstream_validation', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        327 => ['message' => 'سقف استفاده روزانه از متد پر شده است.', 'category' => 'rate_limited', 'matched' => null, 'retryable' => false, 'http_status' => 429],
        328 => ['message' => 'سقف مجاز استفاده از متد پر شده است.', 'category' => 'rate_limited', 'matched' => null, 'retryable' => false, 'http_status' => 429],
        329 => ['message' => 'شماره کلاسه و شماره مشخصه سرویس همخوانی ندارند.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        330 => ['message' => 'تعداد درخواست در ثانیه از سقف تعیین‌شده عبور کرده است.', 'category' => 'rate_limited', 'matched' => null, 'retryable' => true, 'http_status' => 429],
        331 => ['message' => 'سرویس قابل انتقال نیست.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        332 => ['message' => 'شماره موبایل نماینده در سامانه وجود ندارد.', 'category' => 'not_found', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        333 => ['message' => 'شماره موبایل در پایگاه داده وجود ندارد.', 'category' => 'not_found', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        334 => ['message' => 'اعطای این سرویس به شخص حقیقی میسر نیست.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        335 => ['message' => 'نام کاربری شاهکار غیرفعال است.', 'category' => 'upstream_authentication', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        336 => ['message' => 'شماره در فهرست غیرمجاز شاهکار است.', 'category' => 'blocked_identifier', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        340 => ['message' => 'IPهای ورودی با هم تداخل دارند.', 'category' => 'ip_conflict', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        341 => ['message' => 'IPهای ورودی با IP ثبت‌شده دیگران تداخل دارند.', 'category' => 'ip_conflict', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        346 => ['message' => 'IP در فهرست IPهای اختصاص‌یافته وجود ندارد.', 'category' => 'ip_not_found', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        347 => ['message' => 'IP حذف‌شده به سرویس‌های زیرمجموعه اختصاص داده شده است.', 'category' => 'ip_conflict', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        348 => ['message' => 'برای ثبت IP باید شناسه ملی ثبت شده باشد.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        360 => ['message' => 'ثبت این سرویس برای اشخاص حقوقی مجاز نیست.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        361 => ['message' => 'ثبت سیم‌کارت موقت اتباع فقط برای اشخاص حقیقی غیرایرانی مجاز است.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        370 => ['message' => 'سند درخواست‌شده نامعتبر است.', 'category' => 'upstream_validation', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        500 => ['message' => 'سرویس شاهکار دچار مشکل موقت شده است.', 'category' => 'temporary_upstream_error', 'matched' => null, 'retryable' => true, 'http_status' => 503],
        501 => ['message' => 'خطای هم‌زمانی سرویس شاهکار رخ داده است.', 'category' => 'temporary_upstream_error', 'matched' => null, 'retryable' => true, 'http_status' => 503],
        502 => ['message' => 'خطای هم‌زمانی شماره مشخصه سرویس یا شناسه هویتی رخ داده است.', 'category' => 'business_conflict', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        510 => ['message' => 'مشکل موقت در سرور شاهکار رخ داده است.', 'category' => 'temporary_upstream_error', 'matched' => null, 'retryable' => true, 'http_status' => 503],
        600 => ['message' => 'اطلاعات ارسالی با مرجع تطبیق همخوان نیست.', 'category' => 'mismatch', 'matched' => false, 'retryable' => false, 'http_status' => 200],
        601 => ['message' => 'محدودیت سیاست تعداد سرویس یا حداقل سن برقرار است.', 'category' => 'policy_denied', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        602 => ['message' => 'سرویس‌گیرنده در فهرست غیرمجاز است.', 'category' => 'policy_denied', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        603 => ['message' => 'برای این شماره سرویس قبلاً سرویس موبایل ثبت نشده است.', 'category' => 'not_found', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        604 => ['message' => 'محدودیت سیاست تعداد سرویس یا حداقل سن برقرار است.', 'category' => 'policy_denied', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        610 => ['message' => 'شخص در مرجع تطبیق یافت نشد.', 'category' => 'mismatch', 'matched' => false, 'retryable' => false, 'http_status' => 200],
        611 => ['message' => 'شخص در قید حیات نیست.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        612 => ['message' => 'شخص در قید حیات است.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
        615 => ['message' => 'مرجع تطبیق موقتاً پاسخگو نیست.', 'category' => 'temporary_upstream_error', 'matched' => null, 'retryable' => true, 'http_status' => 503],
        616 => ['message' => 'اطلاعات مکانی با مرجع تطبیق همخوان نیست.', 'category' => 'mismatch', 'matched' => false, 'retryable' => false, 'http_status' => 200],
        617 => ['message' => 'سرویس قطع و وصل تلفن همراه موقتاً دچار مشکل است.', 'category' => 'temporary_upstream_error', 'matched' => null, 'retryable' => true, 'http_status' => 503],
        618 => ['message' => 'شخص حقیقی باید زیر حداکثر سن تعیین‌شده باشد.', 'category' => 'business_rule', 'matched' => null, 'retryable' => false, 'http_status' => 502],
    ];

    /**
     * @return array{code: int, message: string, category: string, matched: bool|null, retryable: bool, http_status: int}
     */
    public function map(int $code): array
    {
        return [
            'code' => $code,
            ...(self::RESPONSES[$code] ?? [
                'message' => 'پاسخ شاهکار قابل پردازش نیست.',
                'category' => 'unknown_upstream_response',
                'matched' => null,
                'retryable' => false,
                'http_status' => 502,
            ]),
        ];
    }

    public function isRetryable(int $code): bool
    {
        return $this->map($code)['retryable'];
    }

    /**
     * @return array<int, array{message: string, category: string, matched: bool|null, retryable: bool, http_status: int}>
     */
    public function documentedResponses(): array
    {
        return self::RESPONSES;
    }
}
