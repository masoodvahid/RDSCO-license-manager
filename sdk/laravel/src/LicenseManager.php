<?php

namespace Rdsco\LicenseClient;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Rdsco\LicenseClient\Exceptions\LicenseServerException;
use Rdsco\LicenseClient\Http\ApiClient;
use Throwable;

/**
 * Answers "may this application run?" with as few network calls as possible.
 *
 * - Checks online at most every `check_interval_minutes`.
 * - Trusts only tokens signed by the pinned public key and bound to the
 *   challenge it sent, so answers cannot be forged or replayed.
 * - When the server is unreachable, keeps working until the last valid
 *   token's offline deadline; a revoked/expired answer is never overridden.
 */
class LicenseManager
{
    private ?LicenseStatus $memo = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly ApiClient $api,
        private readonly Repository $cache,
        private readonly array $config,
    ) {}

    public function status(bool $forceRefresh = false): LicenseStatus
    {
        if (! $forceRefresh && $this->memo !== null) {
            return $this->memo;
        }

        return $this->memo = $this->resolve($forceRefresh);
    }

    public function isValid(): bool
    {
        return $this->status()->isValid();
    }

    public function feature(string $key, mixed $default = null): mixed
    {
        return $this->status()->feature($key, $default);
    }

    public function forget(): void
    {
        $this->cache->forget($this->cacheKey());
        $this->memo = null;
    }

    private function resolve(bool $forceRefresh): LicenseStatus
    {
        $publicKey = (string) ($this->config['public_key'] ?? '');

        if ($publicKey === '') {
            return LicenseStatus::invalid('not_configured');
        }

        $cached = $this->cachedToken($publicKey);
        $interval = max(1, (int) ($this->config['check_interval_minutes'] ?? 60)) * 60;

        if (! $forceRefresh && $cached !== null && Carbon::now()->getTimestamp() - $cached['checked_at'] < $interval) {
            return $this->statusFrom($cached['token'], $cached['checked_at'], offline: false);
        }

        // Only one request refreshes at a time; the others keep using the cached answer.
        if (! $forceRefresh && $cached !== null && ! $this->cache->add($this->cacheKey().':refreshing', true, 30)) {
            return $this->statusFrom($cached['token'], $cached['checked_at'], offline: false);
        }

        try {
            $token = $this->fetch($publicKey);
            $checkedAt = Carbon::now()->getTimestamp();
            $this->cache->forever($this->cacheKey(), ['token' => $token->raw, 'checked_at' => $checkedAt]);

            return $this->statusFrom($token, $checkedAt, offline: false);
        } catch (LicenseServerException $exception) {
            if ($exception->isUnreachable() || $exception->retryable || $exception->httpStatus >= 500 || $exception->httpStatus === 429) {
                return $cached === null
                    ? LicenseStatus::invalid('unreachable')
                    : $this->statusFrom($cached['token'], $cached['checked_at'], offline: true);
            }

            // A definite rejection (wrong key, revoked secret, ...).
            $this->cache->forget($this->cacheKey());

            return LicenseStatus::invalid($exception->errorCode);
        } finally {
            $this->cache->forget($this->cacheKey().':refreshing');
        }
    }

    private function fetch(string $publicKey): LicenseToken
    {
        $challenge = bin2hex(random_bytes(16));

        $data = $this->api->post('/api/v1/license/verify', array_filter([
            'domain' => $this->config['domain'] ?? null,
            'instance_id' => $this->instanceId(),
            'app_version' => $this->config['app_version'] ?? null,
            'challenge' => $challenge,
        ], fn ($value) => $value !== null && $value !== ''));

        $token = LicenseToken::verify((string) ($data['token'] ?? ''), $publicKey);

        if ($token === null || ! hash_equals($challenge, (string) $token->challenge()) || $token->keyId() !== $this->config['key_id']) {
            throw new LicenseServerException('invalid_response', 'The license answer is not signed by the expected server.');
        }

        return $token;
    }

    private function statusFrom(LicenseToken $token, int $checkedAt, bool $offline): LicenseStatus
    {
        $stillValid = $token->isValid() && $token->expiresAt() >= Carbon::now()->getTimestamp();

        return new LicenseStatus(
            valid: $stillValid,
            reason: $stillValid ? null : ($token->reason() ?? ($offline ? 'unreachable' : 'expired')),
            license: $token->license(),
            offlineUntil: $token->expiresAt(),
            offline: $offline,
            checkedAt: $checkedAt,
        );
    }

    /**
     * @return array{token: LicenseToken, checked_at: int}|null
     */
    private function cachedToken(string $publicKey): ?array
    {
        try {
            $cached = $this->cache->get($this->cacheKey());
        } catch (Throwable) {
            return null;
        }

        if (! is_array($cached) || ! is_string($cached['token'] ?? null)) {
            return null;
        }

        // Re-verify: the cache is a file the customer can edit.
        $token = LicenseToken::verify($cached['token'], $publicKey);

        return $token === null || $token->keyId() !== $this->config['key_id']
            ? null
            : ['token' => $token, 'checked_at' => (int) ($cached['checked_at'] ?? 0)];
    }

    private function instanceId(): string
    {
        return (string) $this->cache->rememberForever('rdsco-license:instance-id', fn (): string => bin2hex(random_bytes(8)));
    }

    private function cacheKey(): string
    {
        return 'rdsco-license:token:'.sha1((string) ($this->config['key_id'] ?? ''));
    }
}
