<?php

namespace Rdsco\LicenseClient\Exceptions;

use RuntimeException;

/**
 * The license server rejected a request or could not be reached.
 *
 * $errorCode is the server's stable error code (e.g. "invalid_credentials",
 * "service_not_allowed", "shahkar_connection") or "unreachable".
 */
class LicenseServerException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 0,
        public readonly bool $retryable = false,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function isUnreachable(): bool
    {
        return $this->errorCode === 'unreachable';
    }
}
