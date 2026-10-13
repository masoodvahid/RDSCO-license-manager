<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Key/value application settings. Secret values are encrypted with APP_KEY.
 *
 * All settings are loaded in one query and cached, so reads on hot paths
 * cost nothing.
 */
class Setting extends Model
{
    private const CACHE_KEY = 'settings:all';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::rememberForever(self::CACHE_KEY, fn (): array => static::query()
            ->get(['key', 'value', 'is_encrypted'])
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => [$setting->value, $setting->is_encrypted]])
            ->all());

        if (! array_key_exists($key, $settings) || $settings[$key][0] === null || $settings[$key][0] === '') {
            return $default;
        }

        [$value, $isEncrypted] = $settings[$key];

        return $isEncrypted ? Crypt::decryptString($value) : $value;
    }

    public static function set(string $key, mixed $value, bool $encrypt = false): void
    {
        $storedValue = $value === null || $value === '' ? null : (string) $value;

        static::query()->updateOrCreate(['key' => $key], [
            'value' => $encrypt && $storedValue !== null ? Crypt::encryptString($storedValue) : $storedValue,
            'is_encrypted' => $encrypt,
        ]);

        Cache::forget(self::CACHE_KEY);
    }

    public static function has(string $key): bool
    {
        return static::get($key) !== null;
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
