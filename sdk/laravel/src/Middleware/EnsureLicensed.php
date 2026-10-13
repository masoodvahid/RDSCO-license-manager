<?php

namespace Rdsco\LicenseClient\Middleware;

use Closure;
use Illuminate\Http\Request;
use Rdsco\LicenseClient\LicenseManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks requests while the license is not valid.
 *
 * Usage: ->middleware('rdsco.licensed') or 'rdsco.licensed:feature_name'.
 */
class EnsureLicensed
{
    public function __construct(
        private readonly LicenseManager $license,
    ) {}

    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        $status = $this->license->status();

        if (! $status->isValid()) {
            return $this->deny($request, $status->message(), $status->reason);
        }

        if ($feature !== null && ! $status->hasFeature($feature)) {
            return $this->deny($request, 'این قابلیت در لایسنس شما فعال نیست.', 'feature_not_licensed');
        }

        return $next($request);
    }

    private function deny(Request $request, string $message, ?string $reason): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'reason' => $reason], 403);
        }

        return response(
            '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>لایسنس</title></head>'
            .'<body style="font-family:Tahoma,sans-serif;background:#f9fafb;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0">'
            .'<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:32px;max-width:420px;text-align:center">'
            .'<h1 style="font-size:18px;margin:0 0 12px">دسترسی به نرم‌افزار ممکن نیست</h1>'
            .'<p style="color:#4b5563;line-height:1.8;margin:0">'.e($message).'</p>'
            .'<p style="color:#9ca3af;font-size:12px;margin-top:16px">برای تمدید یا بررسی لایسنس با پشتیبانی تماس بگیرید.</p>'
            .'</div></body></html>',
            403,
        );
    }
}
