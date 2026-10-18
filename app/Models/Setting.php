<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Key/value application settings. Secret values are encrypted with APP_KEY.
 *
 * All settings are loaded in one query and cached, and kept in memory for
 * the rest of the request, so reads on hot paths cost nothing after the
 * first one. Decrypted values are memoized too.
 */
class Setting extends Model
{
    private const CACHE_KEY = 'settings:all';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var array<string, array{0: ?string, 1: bool}>|null */
    private static ?array $loaded = null;

    /** @var array<string, string> */
    private static array $decrypted = [];

    /** Application instance the memo belongs to (tests boot a new one per test). */
    private static ?int $loadedFor = null;

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
        $settings = self::memo();

        if (! array_key_exists($key, $settings) || $settings[$key][0] === null || $settings[$key][0] === '') {
            return $default;
        }

        [$value, $isEncrypted] = $settings[$key];

        if (! $isEncrypted) {
            return $value;
        }

        return self::$decrypted[$key] ??= Crypt::decryptString($value);
    }

    /**
     * @return array<string, array{0: ?string, 1: bool}>
     */
    private static function memo(): array
    {
        if (self::$loaded === null || self::$loadedFor !== spl_object_id(app())) {
            self::$loaded = Cache::rememberForever(self::CACHE_KEY, fn (): array => static::query()
                ->get(['key', 'value', 'is_encrypted'])
                ->mapWithKeys(fn (Setting $setting): array => [$setting->key => [$setting->value, $setting->is_encrypted]])
                ->all());
            self::$decrypted = [];
            self::$loadedFor = spl_object_id(app());
        }

        return self::$loaded;
    }

    private static function forgetMemo(): void
    {
        self::$loaded = null;
        self::$decrypted = [];
    }

    public static function set(string $key, mixed $value, bool $encrypt = false): void
    {
        $storedValue = $value === null || $value === '' ? null : (string) $value;

        static::query()->updateOrCreate(['key' => $key], [
            'value' => $encrypt && $storedValue !== null ? Crypt::encryptString($storedValue) : $storedValue,
            'is_encrypted' => $encrypt,
        ]);

        Cache::forget(self::CACHE_KEY);
        self::forgetMemo();
    }

    public static function has(string $key): bool
    {
        return static::get($key) !== null;
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::forgetMemo();
    }
}
