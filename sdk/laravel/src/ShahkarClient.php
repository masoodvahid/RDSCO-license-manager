<?php

namespace Rdsco\LicenseClient;

use Rdsco\LicenseClient\Exceptions\LicenseServerException;
use Rdsco\LicenseClient\Http\ApiClient;

/**
 * Shahkar (national code + mobile ownership) through the license server.
 */
class ShahkarClient
{
    public function __construct(
        private readonly ApiClient $api,
    ) {}

    /**
     * @param  string|null  $reference  Your own unique id for this check (e.g. "signup-1234").
     *                                  Resending the same reference within 24 hours returns the
     *                                  stored answer without a new charge — use it for retries.
     * @return array{matched: bool, code: int, message: string, tracking_id: ?string, reference: ?string, charged: bool, replayed: bool}
     *
     * @throws LicenseServerException When Shahkar gave no answer (check $e->retryable).
     */
    public function verify(string $nationalCode, string $mobile, ?string $reference = null): array
    {
        /** @var array{matched: bool, code: int, message: string, tracking_id: ?string, reference: ?string, charged: bool, replayed: bool} */
        return $this->api->post('/api/v1/shahkar/verify', array_filter([
            'national_code' => $nationalCode,
            'mobile' => $mobile,
            'reference' => $reference,
        ], fn ($value) => $value !== null), timeout: 40);
    }
}
