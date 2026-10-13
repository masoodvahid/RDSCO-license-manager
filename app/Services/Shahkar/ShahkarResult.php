<?php

namespace App\Services\Shahkar;

/**
 * Outcome of one Shahkar verification.
 *
 * matched: true = identity matches, false = definitive mismatch,
 * null = no business answer (error). Only answers with matched !== null are
 * billable, because only those are a result the customer can use.
 */
final readonly class ShahkarResult
{
    public function __construct(
        public ?bool $matched,
        public int $code,
        public string $category,
        public string $message,
        public bool $retryable,
        public int $httpStatus,
        public ?string $trackingId,
        public ?string $requestId,
        public int $durationMs,
        public string $stage = 'verify',
    ) {}

    public function isAnswer(): bool
    {
        return $this->matched !== null;
    }

    public function isBillable(): bool
    {
        return $this->isAnswer();
    }

    public static function fromException(ShahkarException $exception, int $durationMs): self
    {
        $httpStatus = match ($exception->category) {
            'configuration', 'encryption' => 503,
            'connection', 'timeout' => 504,
            default => 502,
        };

        return new self(
            matched: null,
            code: $exception->gatewayStatus ?? 0,
            category: $exception->category,
            message: $exception->getMessage(),
            retryable: $exception->retryable,
            httpStatus: $httpStatus,
            trackingId: null,
            requestId: null,
            durationMs: $durationMs,
            stage: $exception->stage,
        );
    }
}
