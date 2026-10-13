<?php

namespace App\Services\Billing;

use App\Enums\UsageStatus;
use App\Models\Service;
use App\Models\UsageRecord;
use App\Services\Licensing\LicenseSnapshot;

/**
 * Writes one ledger row per service call. Billable rows capture the price in
 * effect for that customer at that moment.
 */
class UsageRecorder
{
    public function record(
        LicenseSnapshot $license,
        Service $service,
        UsageStatus $status,
        bool $billable,
        ?string $reference = null,
        ?string $clientReference = null,
        ?int $upstreamCode = null,
        ?int $durationMs = null,
    ): UsageRecord {
        return UsageRecord::query()->create([
            'customer_id' => $license->customerId,
            'license_id' => $license->id,
            'service_id' => $service->getKey(),
            'status' => $status,
            'billable' => $billable,
            'unit_price' => $billable ? $service->priceFor($license->customerId) : 0,
            'reference' => $reference,
            'client_reference' => $clientReference,
            'upstream_code' => $upstreamCode,
            'duration_ms' => $durationMs,
        ]);
    }
}
