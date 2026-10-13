<?php

namespace App\Services\Licensing;

use App\Enums\CustomerStatus;
use App\Enums\LicenseStatus;
use App\Models\License;
use Illuminate\Support\Carbon;

/**
 * Immutable, cacheable view of everything the API needs to know about a
 * license. Building it needs a few queries; reading it from cache needs none.
 *
 * The secret stays encrypted inside the snapshot (and therefore inside the
 * cache files) and is decrypted only for signature verification.
 */
final readonly class LicenseSnapshot
{
    /**
     * @param  list<string>  $allowedDomains
     * @param  list<string>  $allowedIps
     * @param  array<string, mixed>  $features
     * @param  list<string>  $serviceCodes
     */
    public function __construct(
        public int $id,
        public string $keyId,
        public string $encryptedSecret,
        public LicenseStatus $status,
        public int $customerId,
        public string $customerName,
        public bool $customerActive,
        public int $productId,
        public string $productSlug,
        public string $productName,
        public bool $productActive,
        public array $allowedDomains,
        public array $allowedIps,
        public ?int $maxUsers,
        public array $features,
        public ?string $startsAt,
        public ?string $expiresAt,
        public array $serviceCodes,
    ) {}

    public static function fromModel(License $license): self
    {
        $license->loadMissing(['customer', 'product', 'services']);

        return new self(
            id: $license->getKey(),
            keyId: $license->key_id,
            encryptedSecret: (string) $license->getRawOriginal('secret'),
            status: $license->status,
            customerId: $license->customer_id,
            customerName: (string) $license->customer?->display_name,
            customerActive: $license->customer !== null && ! $license->customer->trashed() && $license->customer->status === CustomerStatus::Active,
            productId: $license->product_id,
            productSlug: (string) $license->product?->slug,
            productName: (string) $license->product?->name,
            productActive: (bool) $license->product?->is_active,
            allowedDomains: array_values(array_map('strval', $license->allowed_domains ?? [])),
            allowedIps: array_values(array_map('strval', $license->allowed_ips ?? [])),
            maxUsers: $license->max_users,
            features: (array) ($license->features ?? []),
            startsAt: $license->starts_at?->toDateString(),
            expiresAt: $license->expires_at?->toDateString(),
            serviceCodes: $license->services->where('is_active', true)->pluck('code')->values()->all(),
        );
    }

    public function secret(): string
    {
        return decrypt($this->encryptedSecret, false);
    }

    public function validUntil(): ?Carbon
    {
        return $this->expiresAt === null ? null : Carbon::parse($this->expiresAt, config('app.timezone'))->endOfDay();
    }

    public function startsAt(): ?Carbon
    {
        return $this->startsAt === null ? null : Carbon::parse($this->startsAt, config('app.timezone'))->startOfDay();
    }

    public function allowsService(string $code): bool
    {
        return in_array($code, $this->serviceCodes, true);
    }
}
