<?php

namespace Rdsco\LicenseClient;

/**
 * The application's current license state, derived from a verified token.
 */
final class LicenseStatus
{
    /**
     * @param  array<string, mixed>  $license
     */
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $reason,
        public readonly array $license,
        public readonly int $offlineUntil,
        public readonly bool $offline,
        public readonly ?int $checkedAt,
    ) {}

    public static function invalid(string $reason): self
    {
        return new self(false, $reason, [], 0, false, null);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function feature(string $key, mixed $default = null): mixed
    {
        $features = $this->license['features'] ?? [];

        return is_array($features) && array_key_exists($key, $features) ? $features[$key] : $default;
    }

    public function hasFeature(string $key): bool
    {
        return (bool) $this->feature($key, false);
    }

    public function maxUsers(): ?int
    {
        return isset($this->license['max_users']) ? (int) $this->license['max_users'] : null;
    }

    public function expiresAt(): ?string
    {
        return $this->license['expires_at'] ?? null;
    }

    public function daysRemaining(): ?int
    {
        return isset($this->license['days_remaining']) ? (int) $this->license['days_remaining'] : null;
    }

    /**
     * Human-readable (Persian) explanation for a denial.
     */
    public function message(): string
    {
        return match ($this->reason) {
            null => 'لایسنس معتبر است.',
            'expired' => 'اعتبار لایسنس این نرم‌افزار به پایان رسیده است.',
            'license_suspended' => 'لایسنس این نرم‌افزار به حالت تعلیق درآمده است.',
            'license_revoked' => 'لایسنس این نرم‌افزار باطل شده است.',
            'customer_suspended' => 'حساب مشتری غیرفعال است.',
            'product_inactive' => 'این محصول غیرفعال است.',
            'not_started' => 'دوره اعتبار لایسنس هنوز شروع نشده است.',
            'ip_not_allowed' => 'این سرور مجاز به استفاده از لایسنس نیست.',
            'domain_not_allowed' => 'این دامنه مجاز به استفاده از لایسنس نیست.',
            'unreachable' => 'ارتباط با سرور لایسنس برقرار نشد و مهلت کار آفلاین به پایان رسیده است.',
            'invalid_credentials', 'missing_credentials' => 'کلید لایسنس نامعتبر است.',
            'invalid_response' => 'پاسخ سرور لایسنس قابل تأیید نیست.',
            'not_configured' => 'تنظیمات لایسنس کامل نیست.',
            default => 'لایسنس معتبر نیست.',
        };
    }
}
