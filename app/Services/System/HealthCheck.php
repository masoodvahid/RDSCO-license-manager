<?php

namespace App\Services\System;

use App\Services\Shahkar\ShahkarCircuit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Host and application checks that matter for API speed on shared hosting.
 *
 * Every check returns: label, status (ok | warn | fail), value and a hint
 * explaining what to do when it is not ok.
 */
class HealthCheck
{
    /**
     * @return list<array{label: string, status: string, value: string, hint: ?string}>
     */
    public function runtime(): array
    {
        $opcache = $this->opcacheEnabled();
        $maxExecution = (int) ini_get('max_execution_time');
        $deadline = (int) config('license.shahkar.deadline_seconds', 15);

        return [
            $this->check(
                'نسخه PHP',
                version_compare(PHP_VERSION, '8.4.0', '>='),
                PHP_VERSION,
                'این نسخه به PHP 8.4 یا بالاتر نیاز دارد.',
            ),
            $this->check(
                'OPcache',
                $opcache,
                $opcache ? 'فعال' : 'غیرفعال',
                'از PHP Selector در DirectAdmin افزونه opcache را فعال کنید. بدون آن هر درخواست چند برابر کندتر است.',
                failWhenFalse: false,
            ),
            $this->check(
                'افزونه sodium (امضای پاسخ‌ها)',
                extension_loaded('sodium'),
                extension_loaded('sodium') ? 'نصب است' : 'نصب نیست؛ نسخه PHP خالص استفاده می‌شود',
                'افزونه sodium را در PHP Selector فعال کنید. نسخه جایگزین درست کار می‌کند ولی امضا را ده‌ها برابر کندتر انجام می‌دهد.',
                failWhenFalse: false,
            ),
            $this->check(
                'سقف زمان اجرای PHP',
                $maxExecution === 0 || $maxExecution > $deadline,
                $maxExecution === 0 ? 'نامحدود' : $maxExecution.' ثانیه',
                "max_execution_time باید بیشتر از سقف زمان استعلام شاهکار ({$deadline} ثانیه) باشد.",
                failWhenFalse: false,
            ),
            $this->check(
                'حالت اجرا',
                app()->isProduction() && ! config('app.debug'),
                (app()->isProduction() ? 'production' : app()->environment()).(config('app.debug') ? ' + debug' : ''),
                'در .env مقدار APP_ENV=production و APP_DEBUG=false باشد. حالت debug کند است و جزئیات خطا را نشان می‌دهد.',
                failWhenFalse: false,
            ),
            $this->check(
                'کش تنظیمات و مسیرها',
                app()->configurationIsCached() && app()->routesAreCached(),
                collect(['config' => app()->configurationIsCached(), 'routes' => app()->routesAreCached(), 'events' => app()->eventsAreCached()])
                    ->map(fn (bool $cached, string $name): string => $name.($cached ? ' ✓' : ' ✗'))
                    ->implode('، '),
                'بعد از نصب هر نسخه خودکار ساخته می‌شود. اگر نیست، یک بار از صفحه بروزرسانی همان نسخه را دوباره نصب کنید.',
                failWhenFalse: false,
            ),
            $this->cacheCheck(),
            $this->databaseCheck(),
        ];
    }

    /**
     * @return array{open: bool, until: ?int}
     */
    public function shahkarCircuit(): array
    {
        $until = app(ShahkarCircuit::class)->openUntil();

        return ['open' => $until !== null, 'until' => $until];
    }

    /**
     * Approximate row counts and sizes of the largest tables (MySQL only).
     *
     * @return list<array{table: string, rows: int, size_mb: float}>
     */
    public function tableSizes(): array
    {
        if (DB::connection()->getDriverName() !== 'mysql' && DB::connection()->getDriverName() !== 'mariadb') {
            return [];
        }

        try {
            return collect(DB::select(
                'SELECT table_name AS name, table_rows AS row_count, (data_length + index_length) AS bytes
                 FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY bytes DESC LIMIT 8'
            ))->map(fn (object $row): array => [
                'table' => (string) $row->name,
                'rows' => (int) $row->row_count,
                'size_mb' => round(((int) $row->bytes) / 1_048_576, 1),
            ])->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function cacheCheck(): array
    {
        $store = config('license.cache_store') ?: config('cache.default');
        $key = 'health:probe:'.Str::random(8);
        $works = false;

        try {
            $cache = Cache::store(config('license.cache_store'));
            $cache->put($key, 'ok', 60);
            $works = $cache->get($key) === 'ok';
            $cache->forget($key);
        } catch (Throwable) {
            $works = false;
        }

        return $this->check(
            'کش ('.$store.')',
            $works,
            $works ? 'خواندن و نوشتن درست کار می‌کند' : 'کار نمی‌کند',
            'پوشه core/storage/framework/cache باید قابل نوشتن باشد. بدون کش، هر درخواست API چند کوئری اضافه به دیتابیس می‌زند.',
        );
    }

    private function databaseCheck(): array
    {
        try {
            $started = hrtime(true);
            DB::select('SELECT 1');
            $ms = (hrtime(true) - $started) / 1e6;

            return $this->check(
                'تأخیر دیتابیس',
                $ms < 20,
                number_format($ms, 1).' میلی‌ثانیه',
                'تأخیر یک کوئری ساده بالاست؛ دیتابیس روی سرور جداگانه یا پرمشغله است.',
                failWhenFalse: false,
            );
        } catch (Throwable) {
            return $this->check('دیتابیس', false, 'در دسترس نیست', 'اطلاعات اتصال دیتابیس در .env را بررسی کنید.');
        }
    }

    /**
     * @return array{label: string, status: string, value: string, hint: ?string}
     */
    private function check(string $label, bool $ok, string $value, string $hint, bool $failWhenFalse = true): array
    {
        return [
            'label' => $label,
            'status' => $ok ? 'ok' : ($failWhenFalse ? 'fail' : 'warn'),
            'value' => $value,
            'hint' => $ok ? null : $hint,
        ];
    }

    private function opcacheEnabled(): bool
    {
        if (! function_exists('opcache_get_status')) {
            return false;
        }

        try {
            $status = @opcache_get_status(false);
        } catch (Throwable) {
            $status = false;
        }

        // Some hosts disable opcache_get_status(); fall back to the ini value.
        return is_array($status) ? (bool) ($status['opcache_enabled'] ?? false) : filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL);
    }
}
