<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request authentication
    |--------------------------------------------------------------------------
    |
    | Every API request is signed with HMAC-SHA256 using the license secret.
    | Requests older than the tolerance are rejected. Nonces are enforced on
    | billable and write endpoints, where a replayed request would cost money
    | or create data.
    |
    */

    'signature_tolerance_seconds' => (int) env('LICENSE_SIGNATURE_TOLERANCE', 300),

    'max_body_bytes' => (int) env('LICENSE_MAX_BODY_BYTES', 65_536),

    /*
    |--------------------------------------------------------------------------
    | Signed license tokens
    |--------------------------------------------------------------------------
    |
    | License verification answers carry an Ed25519-signed token. Client apps
    | keep the last token and may keep working offline until it expires, but
    | never past the license end date.
    |
    */

    'offline_grace_hours' => (int) env('LICENSE_OFFLINE_GRACE_HOURS', 72),

    'issuer' => env('LICENSE_ISSUER', env('APP_URL', 'rdsco')),

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | Resolved licenses are cached for a short time so the hot path does not
    | touch the database. Cache entries are cleared whenever a license,
    | customer or product changes.
    |
    */

    'cache_store' => env('LICENSE_CACHE_STORE'),

    'cache_seconds' => (int) env('LICENSE_CACHE_SECONDS', 300),

    'last_seen_resolution_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | Rate limits (per license, per minute)
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'verify' => (int) env('LICENSE_RATE_VERIFY', 120),
        'shahkar' => (int) env('LICENSE_RATE_SHAHKAR', 60),
        'errors' => (int) env('LICENSE_RATE_ERRORS', 60),
        'per_ip' => (int) env('LICENSE_RATE_PER_IP', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error reports
    |--------------------------------------------------------------------------
    */

    'errors' => [
        'max_trace_bytes' => 20_000,
        'max_context_bytes' => 8_000,
    ],

];
