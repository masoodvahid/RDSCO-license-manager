<?php

namespace App\Support;

/**
 * The installed release version, read from core/release.json (written by the
 * release workflow). Null in development checkouts.
 */
class AppVersion
{
    private static ?string $resolved = null;

    private static bool $loaded = false;

    public static function current(): ?string
    {
        if (! self::$loaded) {
            self::$loaded = true;
            $file = base_path('release.json');

            if (is_file($file)) {
                $release = json_decode((string) file_get_contents($file), true);
                $version = is_array($release) ? ($release['version'] ?? null) : null;
                self::$resolved = is_string($version) && $version !== '' ? $version : null;
            }
        }

        return self::$resolved;
    }

    public static function label(): string
    {
        $version = self::current();

        return $version === null ? 'نسخه توسعه' : 'نسخه '.ltrim($version, 'vV');
    }
}
