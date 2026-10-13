<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'number',
        'customer_id',
        'status',
        'period_start',
        'period_end',
        'subtotal',
        'discount',
        'tax_rate',
        'tax_amount',
        'total',
        'notes',
        'issued_at',
        'paid_at',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paidAmount(): int
    {
        return (int) $this->payments()->sum('amount');
    }
}
