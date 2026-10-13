<?php

namespace App\Services\Shahkar;

use RuntimeException;

/**
 * A failure before Shahkar gave a business answer (configuration,
 * encryption, token or transport). Never billable.
 */
class ShahkarException extends RuntimeException
{
    public function __construct(
        public readonly string $stage,
        public readonly string $category,
        string $message,
        public readonly bool $retryable = false,
        public readonly ?int $gatewayStatus = null,
    ) {
        parent::__construct($message);
    }
}
