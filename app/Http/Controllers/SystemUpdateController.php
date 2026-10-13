<?php

namespace App\Http\Controllers;

use App\Exceptions\UpdateFailed;
use App\Services\Updates\ApplicationUpdater;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * JSON endpoints driven by the "System update" admin page.
 *
 * These are plain routes rather than Livewire actions on purpose: after the
 * install step swaps the code, the next request must not depend on hydrating
 * a component snapshot produced by the previous release.
 */
class SystemUpdateController extends Controller
{
    public function __construct(
        private readonly ApplicationUpdater $updater,
    ) {}

    public function prepare(Request $request): JsonResponse
    {
        return $this->run(function () use ($request): JsonResponse {
            $state = $this->updater->prepareLatestRelease($request->user()?->getKey());

            return $this->succeeded("نسخه {$state['version']} دانلود، بررسی و آماده نصب شد.");
        });
    }

    public function prepareRollback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'backup' => ['required', 'string', 'max:120'],
        ]);

        return $this->run(function () use ($request, $validated): JsonResponse {
            $state = $this->updater->prepareRollback($validated['backup'], $request->user()?->getKey());

            return $this->succeeded("نسخه پشتیبان {$state['version']} آماده بازگردانی شد.");
        });
    }

    public function install(): JsonResponse
    {
        return $this->run(function (): JsonResponse {
            $bypassSecret = $this->updater->state()['bypass_secret'] ?? null;

            if (! is_string($bypassSecret) || $bypassSecret === '') {
                throw new UpdateFailed('ابتدا مرحله آماده‌سازی بروزرسانی را اجرا کنید.');
            }

            // Build the response before any file is replaced, so nothing in the
            // rest of this request is lazily loaded from the new release. The
            // cookie lets this browser keep using the panel in maintenance mode.
            $response = $this->succeeded('فایل‌های نسخه جدید جایگزین شدند.')
                ->withCookie(MaintenanceModeBypassCookie::create($bypassSecret));

            $this->updater->install();

            return $response;
        });
    }

    public function finalize(): JsonResponse
    {
        return $this->run(function (): JsonResponse {
            $result = $this->updater->finalize();

            return $this->succeeded("بروزرسانی به نسخه {$result['version']} با موفقیت انجام شد.");
        });
    }

    public function discard(): JsonResponse
    {
        return $this->run(function (): JsonResponse {
            $this->updater->discardPrepared();

            return $this->succeeded('بروزرسانی آماده‌شده لغو شد.');
        });
    }

    /**
     * @param  callable(): JsonResponse  $step
     */
    private function run(callable $step): JsonResponse
    {
        try {
            return $step();
        } catch (UpdateFailed $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'خطای داخلی در بروزرسانی رخ داد. جزئیات در لاگ سیستم ثبت شد.',
            ], 500);
        }
    }

    private function succeeded(string $message): JsonResponse
    {
        return response()->json(['ok' => true, 'message' => $message]);
    }
}
