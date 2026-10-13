<?php

use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))));

        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies);
        }

        // Verified before the framework boots (storage/framework/maintenance.php),
        // so it must stay unencrypted. Used by the in-app updater.
        $middleware->encryptCookies(except: ['laravel_maintenance']);

        $middleware->redirectGuestsTo(fn (): string => route('login'));
        $middleware->redirectUsersTo(fn (): string => route('dashboard'));

        $middleware->alias([
            'two-factor' => EnsureTwoFactorVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        // The public API always answers with the same envelope and never leaks internals.
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return ApiResponse::error('validation_failed', 'داده‌های ارسالی معتبر نیست.', 422, [
                    'fields' => $exception->errors(),
                ]);
            }

            if ($exception instanceof HttpExceptionInterface) {
                return match ($exception->getStatusCode()) {
                    404, 405 => ApiResponse::error('not_found', 'مسیر درخواستی وجود ندارد.', 404),
                    413 => ApiResponse::error('payload_too_large', 'حجم درخواست بیش از حد مجاز است.', 413),
                    429 => ApiResponse::error('rate_limited', 'تعداد درخواست‌ها بیش از حد مجاز است.', 429, headers: $exception->getHeaders()),
                    503 => ApiResponse::error('maintenance', 'سرویس موقتاً در حال بروزرسانی است.', 503, headers: $exception->getHeaders()),
                    default => ApiResponse::error('http_error', 'درخواست قابل پردازش نیست.', $exception->getStatusCode()),
                };
            }

            return ApiResponse::error('server_error', 'خطای داخلی سرور.', 500);
        });
    })->create();
