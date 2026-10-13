<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use App\Services\Licensing\LicenseCache;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A customer's right to use one product.
 *
 * The key id is public and identifies the license in API headers. The secret
 * never leaves the server after it is shown once; it signs requests (HMAC).
 */
class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'product_id',
        'label',
        'allowed_domains',
        'allowed_ips',
        'max_users',
        'features',
        'starts_at',
        'expires_at',
        'status',
        'notes',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'allowed_domains' => 'array',
            'allowed_ips' => 'array',
            'features' => 'array',
            'max_users' => 'integer',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'status' => LicenseStatus::class,
            'secret_rotated_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (License $license): void {
            $license->key_id ??= static::generateKeyId();
            $license->secret ??= static::generateSecret();
        });

        static::saved(fn (License $license) => $license->forgetCache());
        static::deleted(fn (License $license) => $license->forgetCache());
    }

    public static function generateKeyId(): string
    {
        return 'lk_'.bin2hex(random_bytes(10));
    }

    public static function generateSecret(): string
    {
        return 'sk_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Replace the signing secret and return the new value (shown once).
     */
    public function rotateSecret(): string
    {
        $secret = static::generateSecret();

        $this->forceFill([
            'secret' => $secret,
            'secret_rotated_at' => now(),
        ])->save();

        return $secret;
    }

    public function forgetCache(): void
    {
        app(LicenseCache::class)->forget($this->key_id);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(AppError::class);
    }

    /**
     * Last moment the license is valid: the end of its expiry day.
     */
    public function validUntil(): ?Carbon
    {
        return $this->expires_at?->copy()->endOfDay();
    }

    public function isExpired(?Carbon $now = null): bool
    {
        $validUntil = $this->validUntil();

        return $validUntil !== null && $validUntil->lt($now ?? now());
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expires_at === null ? null : (int) now()->startOfDay()->diffInDays($this->expires_at, false);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('key_id', 'like', "%{$term}%")
                ->orWhere('label', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $customer) => $customer->search($term));
        });
    }
}
