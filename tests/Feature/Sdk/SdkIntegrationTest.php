<?php

namespace Tests\Feature\Sdk;

use App\Enums\LicenseStatus as ServerLicenseStatus;
use App\Models\AppError;
use App\Models\License;
use App\Models\Service;
use App\Models\Setting;
use App\Services\Licensing\SigningKey;
use App\Services\Shahkar\ShahkarEncryptor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Rdsco\LicenseClient\ErrorReporter;
use Rdsco\LicenseClient\Exceptions\LicenseServerException;
use Rdsco\LicenseClient\Http\ApiClient;
use Rdsco\LicenseClient\LicenseManager;
use Rdsco\LicenseClient\ShahkarClient;
use RuntimeException;
use Tests\TestCase;

/**
 * The client SDK (sdk/laravel) against the real server, through the HTTP kernel.
 */
class SdkIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private License $license;

    private bool $serverDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->license = License::factory()->create([
            'allowed_domains' => ['crm.customer.test'],
            'features' => ['reports' => true, 'branches' => 3],
        ]);

        Http::fake(function (Request $request) {
            $url = parse_url($request->url());

            if (($url['host'] ?? '') !== 'license.test') {
                return null; // let other fakes (Shahkar gateway) answer
            }

            if ($this->serverDown) {
                throw new ConnectionException('Connection refused');
            }

            $headers = collect($request->headers())->map(fn (array $values) => $values[0])->all();
            $response = $this->call($request->method(), $url['path'], [], [], [], $this->transformHeadersToServerVars($headers), $request->body());

            return Http::response($response->getContent(), $response->status(), ['Content-Type' => 'application/json']);
        });
    }

    public function test_a_valid_license_is_cached_between_checks(): void
    {
        $manager = $this->manager();
        $status = $manager->status();

        $this->assertTrue($status->isValid());
        $this->assertFalse($status->offline);
        $this->assertTrue($status->hasFeature('reports'));
        $this->assertSame(3, $status->feature('branches'));
        $this->assertSame(10, $status->maxUsers());

        $this->manager()->status();
        Http::assertSentCount(1);
    }

    public function test_revocation_is_seen_on_the_next_check_and_survives_an_outage(): void
    {
        $this->assertTrue($this->manager()->status()->isValid());

        $this->license->update(['status' => ServerLicenseStatus::Revoked]);

        $status = $this->manager()->status(forceRefresh: true);
        $this->assertFalse($status->isValid());
        $this->assertSame('license_revoked', $status->reason);

        // The server going down must not bring a revoked license back.
        $this->serverDown = true;
        $this->assertFalse($this->manager()->status(forceRefresh: true)->isValid());
    }

    public function test_the_app_keeps_working_offline_until_the_grace_period_ends(): void
    {
        $this->assertTrue($this->manager()->status()->isValid());
        $this->serverDown = true;

        $offline = $this->manager()->status(forceRefresh: true);
        $this->assertTrue($offline->isValid());
        $this->assertTrue($offline->offline);

        $this->travel(73)->hours();

        $expired = $this->manager()->status(forceRefresh: true);
        $this->assertFalse($expired->isValid());
        $this->assertSame('unreachable', $expired->reason);
    }

    public function test_a_server_signing_with_another_key_is_not_trusted(): void
    {
        $status = $this->manager(['public_key' => base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()))])->status();

        $this->assertFalse($status->isValid());
        $this->assertSame('invalid_response', $status->reason);
    }

    public function test_a_tampered_cached_token_is_ignored(): void
    {
        $this->assertTrue($this->manager()->status()->isValid());

        $cacheKey = 'rdsco-license:token:'.sha1($this->license->key_id);
        $cached = Cache::store('array')->get($cacheKey);
        $cached['token'] = str_replace('.', '.x', $cached['token']);
        Cache::store('array')->forever($cacheKey, $cached);
        $this->serverDown = true;

        $this->assertSame('unreachable', $this->manager()->status()->reason);
    }

    public function test_a_wrong_secret_is_a_definite_rejection(): void
    {
        $status = $this->manager(['secret' => 'sk_wrong'])->status();

        $this->assertFalse($status->isValid());
        $this->assertSame('invalid_credentials', $status->reason);
    }

    public function test_errors_are_reported_to_the_server(): void
    {
        $reporter = new ErrorReporter($this->api(), Cache::store('array'), $this->config());

        $exception = new RuntimeException('Disk quota exceeded on /var/www');
        $reporter->report($exception, ['order' => 7]);
        $reporter->report($exception); // same error within the throttle window

        $error = AppError::query()->sole();
        $this->assertSame('Disk quota exceeded on /var/www', $error->message);
        $this->assertSame(RuntimeException::class, $error->exception_class);
        $this->assertSame(1, $error->occurrences);
    }

    public function test_shahkar_through_the_sdk(): void
    {
        $this->license->services()->attach(Service::byCode(Service::SHAHKAR));

        foreach ([
            'shahkar.token_url' => 'https://gw.test/token', 'shahkar.verify_url' => 'https://gw.test/verify',
            'shahkar.gateway_authorization' => 'x', 'shahkar.username' => 'u', 'shahkar.password' => 'p', 'shahkar.pid' => '1',
            'shahkar.cra_username' => 'c', 'shahkar.cra_password' => 'c', 'shahkar.rid' => '0501',
            'shahkar.public_key' => "-----BEGIN PUBLIC KEY-----\nX\n-----END PUBLIC KEY-----",
        ] as $key => $value) {
            Setting::set($key, $value);
        }

        $this->mock(ShahkarEncryptor::class)->shouldReceive('encrypt')->andReturn('jwe');
        Http::fake([
            'gw.test/token' => Http::response(['access_token' => 't', 'expires_in' => 300]),
            'gw.test/verify' => Http::response(['result' => ['data' => ['result' => ['data' => ['response' => 600, 'requestId' => '1']]]], 'status' => ['statusCode' => 200]]),
        ]);

        $answer = (new ShahkarClient($this->api()))->verify('0499370899', '09123456789', 'signup-1');

        $this->assertFalse($answer['matched']);
        $this->assertSame(600, $answer['code']);
        $this->assertTrue($answer['charged']);

        $this->expectException(LicenseServerException::class);
        (new ShahkarClient($this->api()))->verify('1234567890', '09123456789');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        return [
            'url' => 'https://license.test',
            'key_id' => $this->license->key_id,
            'secret' => $this->license->secret,
            'public_key' => app(SigningKey::class)->publicKey(),
            'domain' => 'https://crm.customer.test',
            'app_version' => '3.1.0',
            'check_interval_minutes' => 60,
            'timeout_seconds' => 5,
            'report_errors' => true,
            'error_throttle_seconds' => 60,
            'dont_report' => [],
            ...$overrides,
        ];
    }

    private function api(array $overrides = []): ApiClient
    {
        return new ApiClient(Http::getFacadeRoot(), $this->config($overrides));
    }

    private function manager(array $overrides = []): LicenseManager
    {
        return new LicenseManager($this->api($overrides), Cache::store('array'), $this->config($overrides));
    }
}
