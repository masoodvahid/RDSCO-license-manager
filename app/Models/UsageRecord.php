<?php

namespace App\Models;

use App\Enums\UsageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger entry for one service call. The unit price is captured
 * at call time, so later tariff changes never alter past usage.
 */
class UsageRecord extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'license_id',
        'service_id',
        'status',
        'billable',
        'unit_price',
        'reference',
        'client_reference',
        'national_code',
        'mobile',
        'national_code_hash',
        'mobile_hash',
        'upstream_code',
        'upstream_message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'status' => UsageStatus::class,
            'billable' => 'boolean',
            'unit_price' => 'integer',
            'national_code' => 'encrypted',
            'mobile' => 'encrypted',
            'upstream_code' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'national_code',
        'mobile',
        'national_code_hash',
        'mobile_hash',
    ];

    /**
     * Keyed hash used to search encrypted national codes and mobiles.
     */
    public static function lookupHash(string $value): string
    {
        return hash_hmac('sha256', $value, 'usage-subject|'.config('app.key'));
    }

    /**
     * 09121234567 -> 0912***4567, for places where the full number is not needed.
     */
    public function maskedMobile(): ?string
    {
        return $this->mobile === null ? null : substr($this->mobile, 0, 4).'***'.substr($this->mobile, -4);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
