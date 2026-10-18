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
        ?string $upstreamMessage = null,
        ?string $nationalCode = null,
        ?string $mobile = null,
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
            'national_code' => $nationalCode,
            'mobile' => $mobile,
            'national_code_hash' => $nationalCode === null ? null : UsageRecord::lookupHash($nationalCode),
            'mobile_hash' => $mobile === null ? null : UsageRecord::lookupHash($mobile),
            'upstream_code' => $upstreamCode,
            'upstream_message' => $upstreamMessage === null ? null : mb_substr($upstreamMessage, 0, 255),
            'duration_ms' => $durationMs,
        ]);
    }
}
