<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UsageStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateLicense;
use App\Models\Service;
use App\Models\UsageRecord;
use App\Rules\IranianNationalCode;
use App\Services\Billing\UsageRecorder;
use App\Services\Licensing\LicenseActivity;
use App\Services\Licensing\LicensePolicy;
use App\Services\Shahkar\ShahkarClient;
use App\Services\Shahkar\ShahkarResponseCodes;
use App\Support\ApiResponse;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShahkarController extends Controller
{
    /**
     * A client may resend the same "reference" (e.g. after a timeout) within
     * this window and get the stored answer without a new, billed request.
     */
    private const IDEMPOTENCY_WINDOW_HOURS = 24;

    public function __construct(
        private readonly ShahkarClient $shahkar,
        private readonly UsageRecorder $usage,
        private readonly LicenseActivity $activity,
        private readonly ShahkarResponseCodes $codes,
    ) {}

    /**
     * Check that a mobile number belongs to the owner of a national code.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->merge(['mobile' => self::normalizeMobile((string) $request->input('mobile'))]);

        $input = $request->validate([
            'national_code' => ['required', 'string', new IranianNationalCode],
            'mobile' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'reference' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ], [
            'mobile.regex' => 'شماره همراه باید با قالب 09xxxxxxxxx باشد.',
        ]);

        $license = AuthenticateLicense::license($request);

        if (! $license->allowsService(Service::SHAHKAR)) {
            return ApiResponse::error('service_not_allowed', LicensePolicy::MESSAGES['service_not_allowed'], 403);
        }

        $reference = $input['reference'] ?? null;

        if ($reference !== null && ($previous = $this->previousAnswer($license->id, $reference)) !== null) {
            return $this->answer($previous->upstream_code, $previous->reference, $reference, charged: false, replayed: true);
        }

        $service = Service::byCode(Service::SHAHKAR);
        $result = $this->shahkar->verify($input['national_code'], $input['mobile']);

        $this->usage->record(
            license: $license,
            service: $service,
            status: match ($result->matched) {
                true => UsageStatus::Success,
                false => UsageStatus::Failed,
                null => UsageStatus::Error,
            },
            billable: $result->isBillable(),
            reference: $result->trackingId ?? $result->requestId,
            clientReference: $result->isAnswer() ? $reference : null,
            upstreamCode: $result->code,
            durationMs: $result->durationMs,
            upstreamMessage: $result->message,
            nationalCode: $input['national_code'],
            mobile: $input['mobile'],
        );

        $this->activity->touchLastSeen($license, $request->ip(), null);

        if (! $result->isAnswer()) {
            return ApiResponse::error('shahkar_'.$result->category, $result->message, $result->httpStatus, [
                'upstream_code' => $result->code,
                'retryable' => $result->retryable,
            ]);
        }

        return $this->answer($result->code, $result->trackingId ?? $result->requestId, $reference, charged: true, replayed: false);
    }

    private function answer(?int $code, ?string $trackingId, ?string $reference, bool $charged, bool $replayed): JsonResponse
    {
        $mapped = $this->codes->map((int) $code);

        return ApiResponse::success([
            'matched' => $mapped['matched'],
            'code' => (int) $code,
            'message' => $mapped['message'],
            'tracking_id' => $trackingId,
            'reference' => $reference,
            'charged' => $charged,
            'replayed' => $replayed,
        ]);
    }

    private function previousAnswer(int $licenseId, string $reference): ?UsageRecord
    {
        return UsageRecord::query()
            ->where('license_id', $licenseId)
            ->where('client_reference', $reference)
            ->where('created_at', '>=', now()->subHours(self::IDEMPOTENCY_WINDOW_HOURS))
            ->latest('id')
            ->first();
    }

    /**
     * Accept 9xxxxxxxxx, 989xxxxxxxxx, +989xxxxxxxxx and Persian digits.
     */
    public static function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', Jalali::toLatinDigits($mobile)) ?? '';

        return match (true) {
            str_starts_with($digits, '0098') => '0'.substr($digits, 4),
            str_starts_with($digits, '98') && strlen($digits) === 12 => '0'.substr($digits, 2),
            str_starts_with($digits, '9') && strlen($digits) === 10 => '0'.$digits,
            default => $digits,
        };
    }
}
