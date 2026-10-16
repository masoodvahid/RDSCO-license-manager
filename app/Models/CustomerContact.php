<?php

namespace App\Models;

use App\Enums\ContactChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of the conversation log kept for a customer.
 */
class CustomerContact extends Model
{
    protected $fillable = [
        'customer_id',
        'contacted_on',
        'channel',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'contacted_on' => 'date',
            'channel' => ContactChannel::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
