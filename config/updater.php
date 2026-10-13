<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GitHub release source
    |--------------------------------------------------------------------------
    |
    | Updates are installed from the latest *published* GitHub release of this
    | repository. The release must carry the prebuilt package produced by the
    | "Build Release Package" workflow plus its SHA-256 checksum file.
    |
    | A token is only required when the repository is private. Use a
    | fine-grained token with read-only "Contents" permission on this repo.
    |
    */

    'repository' => env('UPDATER_REPOSITORY', 'masoodvahid/rdsco-license-manager'),

    'token' => env('UPDATER_GITHUB_TOKEN'),

    'asset_name' => env('UPDATER_ASSET_NAME', 'rdsco-release.zip'),

    'api_url' => 'https://api.github.com',

    /*
    |--------------------------------------------------------------------------
    | Server layout
    |--------------------------------------------------------------------------
    |
    | The production layout is <domain root>/core (Laravel) and
    | <domain root>/public_html (document root). When the application runs
    | from a directory named "core", the domain root is detected
    | automatically; otherwise set UPDATER_DOMAIN_ROOT explicitly.
    |
    */

    'domain_root' => env('UPDATER_DOMAIN_ROOT'),

    'core_directory' => 'core',

    'public_directory' => 'public_html',

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'max_package_bytes' => (int) env('UPDATER_MAX_PACKAGE_BYTES', 512 * 1024 * 1024),

    'max_uncompressed_bytes' => (int) env('UPDATER_MAX_UNCOMPRESSED_BYTES', 1024 * 1024 * 1024),

    'max_files' => (int) env('UPDATER_MAX_FILES', 50_000),

    'download_timeout_seconds' => (int) env('UPDATER_DOWNLOAD_TIMEOUT', 600),

    'release_cache_seconds' => (int) env('UPDATER_RELEASE_CACHE_SECONDS', 600),

    'keep_backups' => (int) env('UPDATER_KEEP_BACKUPS', 3),

];
