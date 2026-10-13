<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return [

    // Address of the license server, e.g. https://api.rahkar-digital.ir
    'url' => env('RDSCO_LICENSE_URL'),

    // Values from the license page in the admin panel.
    'key_id' => env('RDSCO_LICENSE_KEY_ID'),
    'secret' => env('RDSCO_LICENSE_SECRET'),

    // Ed25519 public key (base64) of the server. Tokens signed with any other
    // key are rejected, so a fake license server cannot unlock the app.
    'public_key' => env('RDSCO_LICENSE_PUBLIC_KEY'),

    // Reported to the server and checked against the license's allowed domains.
    'domain' => env('RDSCO_LICENSE_DOMAIN', env('APP_URL')),

    'app_version' => env('RDSCO_LICENSE_APP_VERSION'),

    // Re-check online at most this often; between checks the signed token is used.
    'check_interval_minutes' => (int) env('RDSCO_LICENSE_CHECK_INTERVAL', 60),

    'timeout_seconds' => (int) env('RDSCO_LICENSE_TIMEOUT', 8),

    // Cache store for the signed token. Use a persistent store (file, redis, database).
    'cache_store' => env('RDSCO_LICENSE_CACHE_STORE'),

    // Send unhandled exceptions to the license server's error log.
    'report_errors' => (bool) env('RDSCO_LICENSE_REPORT_ERRORS', true),

    // Do not report the same error more than once per this many seconds.
    'error_throttle_seconds' => 60,

    // Exceptions that are never reported.
    'dont_report' => [
        AuthenticationException::class,
        ValidationException::class,
        HttpExceptionInterface::class,
        TokenMismatchException::class,
        ModelNotFoundException::class,
    ],

];
