<?php

namespace Tests;

use App\Models\License;
use App\Models\User;
use App\Services\Licensing\RequestSignature;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Send a request signed exactly like a client application would.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function signedJson(
        License $license,
        string $method,
        string $path,
        array $data = [],
        ?string $nonce = null,
        ?int $timestamp = null,
        array $headers = [],
        ?string $secret = null,
    ): TestResponse {
        $body = $method === 'GET' ? '' : (string) json_encode($data);
        $timestamp ??= time();
        $nonce ??= (string) Str::uuid();

        return $this->call($method, $path, [], [], [], $this->transformHeadersToServerVars([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Key-Id' => $license->key_id,
            'X-Timestamp' => (string) $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => RequestSignature::sign($secret ?? $license->secret, $method, $path, (string) $timestamp, $nonce, $body),
            ...$headers,
        ]), $body);
    }

    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->create()->fresh();
        $this->actingAs($admin);

        return $admin;
    }
}
