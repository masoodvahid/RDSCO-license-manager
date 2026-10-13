<?php

namespace App\Services\Licensing;

use App\Enums\LicenseStatus;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Decides whether a license may be used right now, from where.
 */
class LicensePolicy
{
    /**
     * Persian messages for every denial reason (keys are stable API codes).
     */
    public const MESSAGES = [
        'license_suspended' => 'لایسنس به حالت تعلیق درآمده است.',
        'license_revoked' => 'لایسنس باطل شده است.',
        'customer_suspended' => 'حساب مشتری غیرفعال است.',
        'product_inactive' => 'این محصول غیرفعال است.',
        'not_started' => 'دوره اعتبار لایسنس هنوز شروع نشده است.',
        'expired' => 'اعتبار لایسنس به پایان رسیده است.',
        'ip_not_allowed' => 'درخواست از IP مجاز این لایسنس ارسال نشده است.',
        'domain_not_allowed' => 'دامنه اعلام‌شده برای این لایسنس مجاز نیست.',
        'service_not_allowed' => 'این سرویس برای لایسنس فعال نیست.',
    ];

    /**
     * Reason code when the license cannot be used, or null when it can.
     */
    public function denialReason(LicenseSnapshot $license, ?string $ip, ?string $domain = null, ?Carbon $now = null): ?string
    {
        $now ??= now();

        return match (true) {
            $license->status === LicenseStatus::Revoked => 'license_revoked',
            $license->status === LicenseStatus::Suspended => 'license_suspended',
            ! $license->customerActive => 'customer_suspended',
            ! $license->productActive => 'product_inactive',
            $license->startsAt()?->gt($now) === true => 'not_started',
            $license->validUntil()?->lt($now) === true => 'expired',
            ! $this->allowsIp($license, $ip) => 'ip_not_allowed',
            $domain !== null && ! $this->allowsDomain($license, $domain) => 'domain_not_allowed',
            default => null,
        };
    }

    public function allowsIp(LicenseSnapshot $license, ?string $ip): bool
    {
        if ($license->allowedIps === []) {
            return true;
        }

        return $ip !== null && IpUtils::checkIp($ip, $license->allowedIps);
    }

    /**
     * Allowed entries match exactly, ignoring "www."; "*.example.com" also
     * matches any subdomain.
     */
    public function allowsDomain(LicenseSnapshot $license, string $domain): bool
    {
        if ($license->allowedDomains === []) {
            return true;
        }

        $host = self::normalizeHost($domain);

        if ($host === '') {
            return false;
        }

        foreach ($license->allowedDomains as $allowedDomain) {
            $allowed = strtolower(trim($allowedDomain));

            if (str_starts_with($allowed, '*.')) {
                $base = self::normalizeHost(substr($allowed, 2));

                if ($host === $base || str_ends_with($host, '.'.$base)) {
                    return true;
                }

                continue;
            }

            if ($host === self::normalizeHost($allowed)) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeHost(string $value): string
    {
        $value = strtolower(trim($value));
        $host = parse_url(str_contains($value, '://') ? $value : 'https://'.$value, PHP_URL_HOST);

        return is_string($host) ? (string) preg_replace('/^www\./', '', rtrim($host, '.')) : '';
    }
}
