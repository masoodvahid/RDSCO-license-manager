<?php

namespace App\Services\Shahkar;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Circuit breaker for the Sitaad/Shahkar gateway.
 *
 * Every Shahkar call holds a PHP worker until the gateway answers. When the
 * gateway is down, waiting callers would use up all workers of a shared host
 * and take license checks and the panel down with them. After a few transport
 * failures in a short window the circuit opens and calls fail immediately for
 * a cool-down period instead of waiting on timeouts.
 */
class ShahkarCircuit
{
    private const FAILURES_KEY = 'shahkar:circuit:failures';

    private const OPEN_KEY = 'shahkar:circuit:open-until';

    public function isOpen(): bool
    {
        return $this->openUntil() !== null;
    }

    /**
     * Unix time the circuit closes again, or null when it is closed.
     */
    public function openUntil(): ?int
    {
        $until = $this->store()->get(self::OPEN_KEY);

        return is_int($until) && $until > time() ? $until : null;
    }

    public function recordFailure(): void
    {
        $window = max(10, (int) config('license.shahkar.circuit_window_seconds', 60));
        $threshold = max(1, (int) config('license.shahkar.circuit_threshold', 5));
        $cooldown = max(5, (int) config('license.shahkar.circuit_cooldown_seconds', 30));

        $this->store()->add(self::FAILURES_KEY, 0, $window);
        $failures = (int) $this->store()->increment(self::FAILURES_KEY);

        if ($failures >= $threshold) {
            $this->store()->put(self::OPEN_KEY, time() + $cooldown, $cooldown);
            $this->store()->forget(self::FAILURES_KEY);
        }
    }

    public function recordSuccess(): void
    {
        if ($this->store()->has(self::FAILURES_KEY)) {
            $this->store()->forget(self::FAILURES_KEY);
        }
    }

    public function reset(): void
    {
        $this->store()->forget(self::FAILURES_KEY);
        $this->store()->forget(self::OPEN_KEY);
    }

    /**
     * Transport-level problems (no connection, timeout, gateway 5xx) count
     * against the circuit; business answers and validation errors do not.
     */
    public static function isTransportFailure(ShahkarResult $result): bool
    {
        if ($result->category === 'connection' || $result->category === 'timeout') {
            return true;
        }

        return $result->stage === 'gateway' && in_array($result->httpStatus, [503, 504], true);
    }

    private function store(): Repository
    {
        return Cache::store(config('license.cache_store'));
    }
}
