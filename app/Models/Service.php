<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A billable API service (e.g. Shahkar). Rows are seeded by migrations and
 * referenced by code from the API.
 */
class Service extends Model
{
    public const SHAHKAR = 'shahkar';

    protected $fillable = [
        'name',
        'description',
        'unit_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (Service $service) => $service->licenses()->each(fn (License $license) => $license->forgetCache()));
    }

    public static function byCode(string $code): self
    {
        return static::query()->where('code', $code)->firstOrFail();
    }

    public function licenses(): BelongsToMany
    {
        return $this->belongsToMany(License::class);
    }

    public function customerPrices(): HasMany
    {
        return $this->hasMany(CustomerServicePrice::class);
    }

    /**
     * Price for a customer: their negotiated price, or the default.
     */
    public function priceFor(int $customerId): int
    {
        $customPrice = CustomerServicePrice::query()
            ->where('customer_id', $customerId)
            ->where('service_id', $this->getKey())
            ->value('unit_price');

        return $customPrice === null ? $this->unit_price : (int) $customPrice;
    }
}
