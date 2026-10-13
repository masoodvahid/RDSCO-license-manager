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
        'upstream_code',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'status' => UsageStatus::class,
            'billable' => 'boolean',
            'unit_price' => 'integer',
            'upstream_code' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
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
