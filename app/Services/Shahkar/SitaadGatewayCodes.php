<?php

namespace App\Services\Shahkar;

class SitaadGatewayCodes
{
    /**
     * @var array<int, array{message: string, category: string, retryable: bool, http_status: int}>
     */
    private const RESPONSES = [
        200 => ['message' => 'عملیات سیتاد با موفقیت انجام شد.', 'category' => 'ok', 'retryable' => false, 'http_status' => 200],
        202 => ['message' => 'درخواست توسط سیتاد دریافت شده است.', 'category' => 'accepted', 'retryable' => true, 'http_status' => 503],
        400 => ['message' => 'سیتاد داده ورودی را نپذیرفت.', 'category' => 'bad_request', 'retryable' => false, 'http_status' => 502],
        401 => ['message' => 'اعتبارنامه اتصال به سیتاد معتبر نیست.', 'category' => 'authentication', 'retryable' => false, 'http_status' => 502],
        403 => ['message' => 'IP سرور در سیتاد مجاز نیست یا مسدود شده است.', 'category' => 'forbidden_ip', 'retryable' => false, 'http_status' => 502],
        404 => ['message' => 'منبع درخواستی در سیتاد پیدا نشد.', 'category' => 'not_found', 'retryable' => false, 'http_status' => 502],
        408 => ['message' => 'سیتاد در زمان تعیین‌شده پاسخ نداد.', 'category' => 'timeout', 'retryable' => true, 'http_status' => 504],
        410 => ['message' => 'آدرس سیتاد باید با HTTPS فراخوانی شود.', 'category' => 'https_required', 'retryable' => false, 'http_status' => 502],
        429 => ['message' => 'سقف مجاز درخواست سیتاد پر شده یا دسترسی سرویس محدود شده است.', 'category' => 'rate_limited', 'retryable' => true, 'http_status' => 429],
        499 => ['message' => 'ارتباط با سیتاد هنگام پردازش قطع شده است.', 'category' => 'client_closed_request', 'retryable' => false, 'http_status' => 502],
        500 => ['message' => 'سیتاد با خطای داخلی مواجه شده است.', 'category' => 'server_error', 'retryable' => true, 'http_status' => 503],
        503 => ['message' => 'ارائه‌دهنده سرویس سیتاد موقتاً در دسترس نیست.', 'category' => 'service_provider_error', 'retryable' => true, 'http_status' => 503],
        504 => ['message' => 'Backend سیتاد در زمان تعیین‌شده پاسخ نداد.', 'category' => 'backend_timeout', 'retryable' => true, 'http_status' => 504],
        520 => ['message' => 'سیتاد خطای ناشناخته برگرداند.', 'category' => 'unknown_error', 'retryable' => true, 'http_status' => 503],
    ];

    /**
     * @return array{code: int, message: string, category: string, retryable: bool, http_status: int}
     */
    public function map(int $statusCode): array
    {
        return [
            'code' => $statusCode,
            ...(self::RESPONSES[$statusCode] ?? [
                'message' => 'پاسخ سیتاد قابل پردازش نیست.',
                'category' => 'unknown_gateway_response',
                'retryable' => false,
                'http_status' => 502,
            ]),
        ];
    }

    public function isRetryable(int $statusCode): bool
    {
        return $this->map($statusCode)['retryable'];
    }

    /**
     * @return array<int, array{message: string, category: string, retryable: bool, http_status: int}>
     */
    public function documentedResponses(): array
    {
        return self::RESPONSES;
    }
}
