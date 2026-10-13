<?php

namespace App\Models;

use App\Enums\ErrorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An error reported by a connected application. Identical errors from the
 * same license are grouped by fingerprint and counted.
 */
class AppError extends Model
{
    protected $fillable = [
        'license_id',
        'customer_id',
        'product_id',
        'fingerprint',
        'level',
        'message',
        'exception_class',
        'file',
        'line',
        'trace',
        'context',
        'url',
        'environment',
        'app_version',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
        'status',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'line' => 'integer',
            'occurrences' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'status' => ErrorStatus::class,
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
