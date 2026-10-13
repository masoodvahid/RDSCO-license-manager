<?php

namespace Rdsco\LicenseClient;

use Illuminate\Contracts\Cache\Repository;
use Rdsco\LicenseClient\Http\ApiClient;
use Throwable;

/**
 * Sends exceptions to the license server's error log.
 *
 * Never throws, sends after the response when possible, and reports the
 * same error at most once per throttle window.
 */
class ErrorReporter
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly ApiClient $api,
        private readonly Repository $cache,
        private readonly array $config,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function report(Throwable $exception, array $context = [], string $level = 'error'): void
    {
        if (! ($this->config['report_errors'] ?? true) || $this->shouldIgnore($exception)) {
            return;
        }

        $throttleKey = 'rdsco-license:error:'.sha1($exception::class.$exception->getFile().$exception->getLine());

        try {
            if (! $this->cache->add($throttleKey, true, max(1, (int) ($this->config['error_throttle_seconds'] ?? 60)))) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $payload = [
            'message' => mb_substr($exception->getMessage() ?: $exception::class, 0, 10_000),
            'level' => $level,
            'exception_class' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => mb_substr($exception->getTraceAsString(), 0, 50_000),
            'context' => $context === [] ? null : $context,
            'url' => app()->runningInConsole() ? 'artisan '.implode(' ', array_slice($_SERVER['argv'] ?? [], 1)) : request()->fullUrl(),
            'environment' => app()->environment(),
            'app_version' => $this->config['app_version'] ?? null,
        ];

        $send = function () use ($payload): void {
            try {
                $this->api->post('/api/v1/errors', array_filter($payload, fn ($value) => $value !== null), timeout: 5);
            } catch (Throwable) {
                // Reporting must never break the application.
            }
        };

        if (app()->runningInConsole()) {
            $send();
        } else {
            app()->terminating($send);
        }
    }

    private function shouldIgnore(Throwable $exception): bool
    {
        foreach ((array) ($this->config['dont_report'] ?? []) as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
