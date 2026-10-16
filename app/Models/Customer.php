<?php

namespace App\Models;

use App\Enums\ContactChannel;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'company_name',
        'national_id',
        'registration_number',
        'mobile',
        'email',
        'website',
        'contact_channels',
        'address',
        'postal_code',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'contact_channels' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // License answers include customer data and status; refresh cached copies.
        static::saved(fn (Customer $customer) => $customer->licenses()->each(fn (License $license) => $license->forgetCache()));
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function servicePrices(): HasMany
    {
        return $this->hasMany(CustomerServicePrice::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /**
     * Preferred messengers, in the order they are offered in the form.
     *
     * @return list<ContactChannel>
     */
    public function channels(): array
    {
        $selected = (array) ($this->contact_channels ?? []);

        return array_values(array_filter(ContactChannel::cases(), fn (ContactChannel $channel): bool => in_array($channel->value, $selected, true)));
    }

    /**
     * Website as a clickable absolute URL.
     */
    public function websiteUrl(): ?string
    {
        if (blank($this->website)) {
            return null;
        }

        return preg_match('#^https?://#i', $this->website) === 1 ? $this->website : 'https://'.$this->website;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?: $this->name;
    }

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    /**
     * Invoiced total minus payments. Positive means the customer owes money.
     */
    public function balance(): int
    {
        $invoiced = (int) $this->invoices()->where('status', '!=', InvoiceStatus::Void)->sum('total');
        $paid = (int) $this->payments()->sum('amount');

        return $invoiced - $paid;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%")
                ->orWhere('national_id', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
