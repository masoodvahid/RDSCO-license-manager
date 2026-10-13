<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerServicePrice extends Model
{
    protected $fillable = [
        'customer_id',
        'service_id',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
