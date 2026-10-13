<?php

namespace App\Services\Errors;

use App\Enums\ErrorStatus;
use App\Models\AppError;
use App\Services\Licensing\LicenseSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Stores error reports from connected applications, grouped by fingerprint.
 *
 * A repeated error increments its counter and refreshes the latest details
 * instead of creating a new row. A resolved error that happens again is
 * reopened; an ignored one stays ignored.
 */
class ErrorReportIngestor
{
    private const SENSITIVE_KEY_PATTERN = '/pass(word|wd)?|secret|token|authorization|api[_-]?key|cookie|national|mobile|card|iban|otp/i';

    /**
     * @param  array<string, mixed>  $report
     */
    public function ingest(LicenseSnapshot $license, array $report): AppError
    {
        $fingerprint = $this->fingerprint($report);
        $now = now();

        $details = [
            'level' => $report['level'] ?? 'error',
            'message' => Str::limit((string) $report['message'], 4000, ''),
            'exception_class' => $report['exception_class'] ?? null,
            'file' => isset($report['file']) ? Str::limit((string) $report['file'], 500, '') : null,
            'line' => $report['line'] ?? null,
            'trace' => isset($report['trace']) ? Str::limit((string) $report['trace'], (int) config('license.errors.max_trace_bytes', 20_000), '') : null,
            'context' => $this->sanitizeContext($report['context'] ?? null),
            'url' => isset($report['url']) ? Str::limit((string) $report['url'], 1000, '') : null,
            'environment' => $report['environment'] ?? null,
            'app_version' => $report['app_version'] ?? null,
            'last_seen_at' => $now,
        ];

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $error = AppError::query()
                ->where('license_id', $license->id)
                ->where('fingerprint', $fingerprint)
                ->first();

            if ($error !== null) {
                $error->fill($details);
                $error->occurrences++;

                if ($error->status === ErrorStatus::Resolved) {
                    $error->status = ErrorStatus::Open;
                    $error->resolved_at = null;
                }

                $error->save();

                return $error;
            }

            try {
                return AppError::query()->create([
                    ...$details,
                    'license_id' => $license->id,
                    'customer_id' => $license->customerId,
                    'product_id' => $license->productId,
                    'fingerprint' => $fingerprint,
                    'occurrences' => 1,
                    'first_seen_at' => $now,
                    'status' => ErrorStatus::Open,
                ]);
            } catch (QueryException $exception) {
                // Another request created the same group concurrently; update it instead.
                if ($attempt === 1) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Unreachable.');
    }

    /**
     * Same class + location + message shape = same error. Numbers and ids in
     * the message are normalized so "User 15 not found" groups with "User 16".
     *
     * @param  array<string, mixed>  $report
     */
    public function fingerprint(array $report): string
    {
        $message = (string) $report['message'];
        $message = (string) preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '{uuid}', $message);
        $message = (string) preg_replace('/\d+/', '{n}', $message);

        return hash('sha256', implode('|', [
            $report['exception_class'] ?? '',
            $report['file'] ?? '',
            $report['line'] ?? '',
            mb_substr($message, 0, 500),
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sanitizeContext(mixed $context): ?array
    {
        if (! is_array($context)) {
            return null;
        }

        $redacted = $this->redact($context);
        $encoded = json_encode($redacted, JSON_UNESCAPED_UNICODE);

        if ($encoded === false || strlen($encoded) > (int) config('license.errors.max_context_bytes', 8_000)) {
            return ['_truncated' => true];
        }

        return $redacted;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function redact(array $values, int $depth = 0): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $depth >= 5 ? '[…]' : $this->redact($value, $depth + 1);
            }
        }

        return $values;
    }
}
