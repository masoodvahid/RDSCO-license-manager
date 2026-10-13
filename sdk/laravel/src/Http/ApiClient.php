<?php

namespace Rdsco\LicenseClient\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Rdsco\LicenseClient\Exceptions\LicenseServerException;

/**
 * Sends signed requests to the license server and unwraps its envelope:
 * {"ok": true, "data": {...}} or {"ok": false, "error": {"code", "message"}}.
 */
class ApiClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly Factory $http,
        private readonly array $config,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload, ?int $timeout = null): array
    {
        $baseUrl = rtrim((string) ($this->config['url'] ?? ''), '/');
        $keyId = (string) ($this->config['key_id'] ?? '');
        $secret = (string) ($this->config['secret'] ?? '');

        if ($baseUrl === '' || $keyId === '' || $secret === '') {
            throw new LicenseServerException('not_configured', 'RDSCO license client is not configured (URL, key id or secret missing).');
        }

        $path = '/'.ltrim($path, '/');
        $body = (string) json_encode($payload === [] ? new \stdClass : $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) Carbon::now()->getTimestamp();
        $nonce = RequestSigner::nonce();

        try {
            $response = $this->http
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Key-Id' => $keyId,
                    'X-Timestamp' => $timestamp,
                    'X-Nonce' => $nonce,
                    'X-Signature' => RequestSigner::sign($secret, 'POST', $path, $timestamp, $nonce, $body),
                ])
                ->withBody($body, 'application/json')
                ->connectTimeout(min(5, $timeout ?? (int) ($this->config['timeout_seconds'] ?? 8)))
                ->timeout($timeout ?? (int) ($this->config['timeout_seconds'] ?? 8))
                ->post($baseUrl.$path);
        } catch (ConnectionException $exception) {
            throw new LicenseServerException('unreachable', 'License server is unreachable: '.$exception->getMessage(), 0, true);
        }

        $json = $response->json();

        if (! is_array($json) || ! array_key_exists('ok', $json)) {
            throw new LicenseServerException('unreachable', "License server returned an unexpected response (HTTP {$response->status()}).", $response->status(), $response->serverError());
        }

        if ($json['ok'] !== true) {
            $error = is_array($json['error'] ?? null) ? $json['error'] : [];

            throw new LicenseServerException(
                (string) ($error['code'] ?? 'error'),
                (string) ($error['message'] ?? 'License server error.'),
                $response->status(),
                (bool) ($error['retryable'] ?? $response->serverError()),
                $error,
            );
        }

        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }
}
