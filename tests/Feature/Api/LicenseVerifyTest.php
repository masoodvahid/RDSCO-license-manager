<?php

namespace Tests\Feature\Api;

use App\Enums\LicenseStatus;
use App\Models\Customer;
use App\Models\License;
use App\Services\Licensing\LicenseTokenIssuer;
use App\Services\Licensing\RequestSignature;
use App\Services\Licensing\SigningKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LicenseVerifyTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/api/v1/license/verify';

    public function test_a_valid_license_gets_a_signed_token_that_verifies_with_the_public_key(): void
    {
        $license = License::factory()->create([
            'allowed_domains' => ['crm.example.com'],
            'features' => ['reports' => true],
            'app_url' => 'crm.example.com',
        ]);

        $response = $this->signedJson($license, 'POST', self::PATH, [
            'domain' => 'https://www.crm.example.com/login',
            'instance_id' => 'server-1',
            'app_version' => '2.4.0',
            'challenge' => 'abcdef0123456789',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.reason', null)
            ->assertJsonPath('data.license.key_id', $license->key_id)
            ->assertJsonPath('data.license.features.reports', true)
            ->assertJsonPath('data.license.code', $license->code)
            ->assertJsonPath('data.license.app_url', 'https://crm.example.com');

        $this->assertMatchesRegularExpression('/^[1-9]\d{5}$/', $license->code);

        $claims = LicenseTokenIssuer::verify($response->json('data.token'), app(SigningKey::class)->publicKey());

        $this->assertNotNull($claims);
        $this->assertTrue($claims['valid']);
        $this->assertSame('abcdef0123456789', $claims['chl']);
        $this->assertSame('crm.example.com', $claims['dom']);
        $this->assertSame($license->key_id, $claims['sub']);
        $this->assertEqualsWithDelta(now()->addHours(72)->timestamp, $claims['exp'], 5);

        $this->assertDatabaseHas('license_daily_stats', ['license_id' => $license->id, 'verifications' => 1, 'rejections' => 0]);
        $this->assertSame('2.4.0', $license->fresh()->last_seen_version);
    }

    public function test_a_tampered_token_does_not_verify(): void
    {
        $license = License::factory()->create();
        $token = $this->signedJson($license, 'POST', self::PATH)->json('data.token');

        [$prefix, $payload, $signature] = explode('.', $token);
        $claims = json_decode(LicenseTokenIssuer::base64UrlDecode($payload), true);
        $claims['valid'] = true;
        $claims['exp'] = now()->addYears(10)->timestamp;
        $forged = $prefix.'.'.LicenseTokenIssuer::base64UrlEncode(json_encode($claims)).'.'.$signature;

        $this->assertNull(LicenseTokenIssuer::verify($forged, app(SigningKey::class)->publicKey()));
    }

    public function test_offline_validity_never_exceeds_the_license_end(): void
    {
        $license = License::factory()->create(['expires_at' => now()->toDateString()]);

        $response = $this->signedJson($license, 'POST', self::PATH)->assertOk();

        $claims = LicenseTokenIssuer::verify($response->json('data.token'), app(SigningKey::class)->publicKey());
        $this->assertTrue($claims['valid'], 'A license is valid through the whole expiry day.');
        $this->assertSame(now()->endOfDay()->timestamp, $claims['exp']);
    }

    public function test_denied_licenses_get_a_signed_negative_answer(): void
    {
        $cases = [
            'expired' => License::factory()->expired()->create(),
            'license_suspended' => License::factory()->suspended()->create(),
            'license_revoked' => License::factory()->create(['status' => LicenseStatus::Revoked]),
            'customer_suspended' => License::factory()->for(Customer::factory()->suspended())->create(),
            'not_started' => License::factory()->create(['starts_at' => now()->addDays(3)->toDateString()]),
            'ip_not_allowed' => License::factory()->create(['allowed_ips' => ['10.0.0.0/8']]),
            'domain_not_allowed' => License::factory()->create(['allowed_domains' => ['shop.example.com']]),
        ];

        foreach ($cases as $reason => $license) {
            $response = $this->signedJson($license, 'POST', self::PATH, ['domain' => 'evil.example.org'])
                ->assertOk()
                ->assertJsonPath('data.valid', false)
                ->assertJsonPath('data.reason', $reason);

            $claims = LicenseTokenIssuer::verify($response->json('data.token'), app(SigningKey::class)->publicKey());
            $this->assertFalse($claims['valid'], $reason);
            $this->assertSame($claims['iat'], $claims['exp'], "{$reason}: a negative answer must not allow offline use.");
        }

        $this->assertSame(count($cases), (int) DB::table('license_daily_stats')->sum('rejections'));
    }

    public function test_wildcard_domains_and_ip_ranges_are_allowed(): void
    {
        $license = License::factory()->create([
            'allowed_domains' => ['*.example.com'],
            'allowed_ips' => ['127.0.0.0/8'],
        ]);

        $this->signedJson($license, 'POST', self::PATH, ['domain' => 'tenant1.example.com'])
            ->assertJsonPath('data.valid', true);

        $this->signedJson($license, 'POST', self::PATH, ['domain' => 'example.com.evil.org'])
            ->assertJsonPath('data.reason', 'domain_not_allowed');
    }

    public function test_requests_with_bad_credentials_are_rejected(): void
    {
        $license = License::factory()->create();

        $this->postJson(self::PATH)->assertStatus(401)->assertJsonPath('error.code', 'missing_credentials');

        $this->signedJson($license, 'POST', self::PATH, secret: 'sk_wrong')
            ->assertStatus(401)->assertJsonPath('error.code', 'invalid_credentials');

        $this->signedJson($license, 'POST', self::PATH, timestamp: time() - 3600)
            ->assertStatus(401)->assertJsonPath('error.code', 'stale_request');

        $unknown = License::factory()->make(['key_id' => 'lk_'.str_repeat('a', 20), 'secret' => 'sk_x']);
        $this->signedJson($unknown, 'POST', self::PATH)
            ->assertStatus(401)->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_the_body_is_covered_by_the_signature(): void
    {
        $license = License::factory()->create();
        $timestamp = time();
        $nonce = 'nonce-'.str_repeat('x', 16);

        $signature = RequestSignature::sign($license->secret, 'POST', self::PATH, (string) $timestamp, $nonce, (string) json_encode(['domain' => 'a.example.com']));

        // Valid signature for one body, sent with another.
        $this->call('POST', self::PATH, [], [], [], $this->transformHeadersToServerVars([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Key-Id' => $license->key_id,
            'X-Timestamp' => (string) $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
        ]), (string) json_encode(['domain' => 'b.example.com']))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_license_changes_are_visible_immediately_despite_caching(): void
    {
        $license = License::factory()->create();

        $this->signedJson($license, 'POST', self::PATH)->assertJsonPath('data.valid', true);

        $license->update(['status' => LicenseStatus::Suspended]);

        $this->signedJson($license, 'POST', self::PATH)->assertJsonPath('data.reason', 'license_suspended');

        $license->customer->update(['status' => 'suspended']);
        $license->update(['status' => LicenseStatus::Active]);

        $this->signedJson($license, 'POST', self::PATH)->assertJsonPath('data.reason', 'customer_suspended');
    }

    public function test_public_key_and_ping_endpoints_are_open(): void
    {
        $this->getJson('/api/v1/public-key')
            ->assertOk()
            ->assertJsonPath('data.algorithm', 'Ed25519')
            ->assertJsonPath('data.public_key', app(SigningKey::class)->publicKey());

        $this->getJson('/api/v1/ping')->assertOk()->assertJsonStructure(['ok', 'data' => ['server_time']]);
        $this->getJson('/api/v1/nope')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }
}
