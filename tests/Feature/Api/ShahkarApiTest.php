<?php

namespace Tests\Feature\Api;

use App\Enums\UsageStatus;
use App\Models\CustomerServicePrice;
use App\Models\License;
use App\Models\Service;
use App\Models\Setting;
use App\Models\UsageRecord;
use App\Services\Shahkar\ShahkarCircuit;
use App\Services\Shahkar\ShahkarEncryptor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShahkarApiTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/api/v1/shahkar/verify';

    private const TOKEN_URL = 'https://gw.test/oauth/token';

    private const VERIFY_URL = 'https://gw.test/shahkar/verify';

    private const NATIONAL_CODE = '0499370899';

    private License $license;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'shahkar.token_url' => self::TOKEN_URL,
            'shahkar.verify_url' => self::VERIFY_URL,
            'shahkar.gateway_authorization' => 'Basic Y2xpZW50OnNlY3JldA==',
            'shahkar.username' => 'gw-user',
            'shahkar.password' => 'gw-pass',
            'shahkar.pid' => 'pid-123',
            'shahkar.cra_username' => 'cra-user',
            'shahkar.cra_password' => 'cra-pass',
            'shahkar.rid' => '0501',
            'shahkar.public_key' => "-----BEGIN PUBLIC KEY-----\nTEST\n-----END PUBLIC KEY-----",
        ] as $key => $value) {
            Setting::set($key, $value, encrypt: str_contains($key, 'password'));
        }

        // Encryption is covered by its own unit test; here we check the protocol.
        $this->mock(ShahkarEncryptor::class)
            ->shouldReceive('encrypt')
            ->andReturnUsing(fn (string $value): string => 'jwe('.$value.')');

        Service::query()->where('code', Service::SHAHKAR)->update(['unit_price' => 15_000]);

        $this->license = License::factory()->create();
        $this->license->services()->attach(Service::byCode(Service::SHAHKAR));
    }

    public function test_a_match_is_returned_and_billed_at_the_customer_price(): void
    {
        CustomerServicePrice::query()->create([
            'customer_id' => $this->license->customer_id,
            'service_id' => Service::byCode(Service::SHAHKAR)->id,
            'unit_price' => 9_000,
        ]);

        $this->fakeGateway([$this->answer(200)]);

        $this->signedJson($this->license, 'POST', self::PATH, [
            'national_code' => self::NATIONAL_CODE,
            'mobile' => '+98 912 345 6789',
        ])
            ->assertOk()
            ->assertJsonPath('data.matched', true)
            ->assertJsonPath('data.code', 200)
            ->assertJsonPath('data.charged', true)
            ->assertJsonPath('data.tracking_id', '050120260101120000000001');

        $record = UsageRecord::query()->sole();
        $this->assertSame(UsageStatus::Success, $record->status);
        $this->assertTrue($record->billable);
        $this->assertSame(9_000, $record->unit_price);

        // The checked person is kept for the usage report, encrypted at rest and searchable by hash.
        $this->assertSame(self::NATIONAL_CODE, $record->national_code);
        $this->assertSame('09123456789', $record->mobile);
        $raw = DB::table('usage_records')->where('id', $record->id)->first();
        $this->assertStringNotContainsString(self::NATIONAL_CODE, (string) $raw->national_code);
        $this->assertStringNotContainsString('09123456789', (string) $raw->mobile);
        $this->assertSame(UsageRecord::lookupHash('09123456789'), $raw->mobile_hash);
        $this->assertArrayNotHasKey('national_code', $record->toArray());

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== self::VERIFY_URL) {
                return false;
            }

            return $request->header('pid')[0] === 'pid-123'
                && $request->header('Authorization')[0] === 'Bearer token-1'
                && $request->header('basicAuthorization')[0] === 'Basic '.base64_encode('cra-user:cra-pass')
                && $request['identificationType'] === 0
                && $request['serviceType'] === 2
                && $request['identificationNo'] === 'jwe('.self::NATIONAL_CODE.')'
                && $request['serviceNumber'] === 'jwe(09123456789)'
                && str_starts_with($request['requestId'], '0501');
        });
    }

    public function test_a_mismatch_is_a_billable_answer(): void
    {
        $this->fakeGateway([$this->answer(600)]);

        $this->signedJson($this->license, 'POST', self::PATH, $this->validInput())
            ->assertOk()
            ->assertJsonPath('data.matched', false)
            ->assertJsonPath('data.code', 600);

        $record = UsageRecord::query()->sole();
        $this->assertSame(UsageStatus::Failed, $record->status);
        $this->assertTrue($record->billable);
        $this->assertSame(15_000, $record->unit_price);
    }

    public function test_upstream_errors_are_reported_and_never_billed(): void
    {
        $this->fakeGateway([Http::response(['status' => ['statusCode' => 403, 'message' => 'Your origin IP is permanently blocked!']], 200)]);

        $this->signedJson($this->license, 'POST', self::PATH, $this->validInput())
            ->assertStatus(502)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'shahkar_forbidden_ip')
            ->assertJsonPath('error.upstream_code', 403);

        $record = UsageRecord::query()->sole();
        $this->assertSame(UsageStatus::Error, $record->status);
        $this->assertFalse($record->billable);
        $this->assertSame(0, $record->unit_price);
    }

    public function test_the_circuit_opens_after_repeated_connection_failures_and_fails_fast(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-1', 'token_type' => 'bearer', 'expires_in' => 359]),
            self::VERIFY_URL => Http::failedConnection(),
        ]);

        // Two calls x three attempts = six transport failures, above the threshold of five.
        foreach ([1, 2] as $call) {
            $this->signedJson($this->license, 'POST', self::PATH, $this->validInput())
                ->assertStatus(504)
                ->assertJsonPath('error.code', 'shahkar_connection');
        }

        $sent = count(Http::recorded());

        $this->signedJson($this->license, 'POST', self::PATH, $this->validInput())
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'shahkar_circuit_open')
            ->assertJsonPath('error.retryable', true);

        $this->assertCount($sent, Http::recorded(), 'No request may reach the gateway while the circuit is open.');
        $this->assertSame(0, UsageRecord::query()->where('billable', true)->count());

        // After a reset (e.g. saving settings), calls go through again.
        app(ShahkarCircuit::class)->reset();
        $this->assertFalse(app(ShahkarCircuit::class)->isOpen());
    }

    public function test_business_answers_do_not_count_against_the_circuit(): void
    {
        config(['license.shahkar.circuit_threshold' => 2]);
        $this->fakeGateway([$this->answer(600), $this->answer(600), $this->answer(600)]);

        foreach ([1, 2, 3] as $call) {
            $this->signedJson($this->license, 'POST', self::PATH, $this->validInput())->assertOk();
        }

        $this->assertFalse(app(ShahkarCircuit::class)->isOpen());
    }

    public function test_transient_failures_are_retried_with_a_new_request_id(): void
    {
        $this->fakeGateway([$this->answer(501), Http::response('', 503), $this->answer(200)]);

        $this->signedJson($this->license, 'POST', self::PATH, $this->validInput())
            ->assertOk()
            ->assertJsonPath('data.matched', true);

        $requestIds = collect(Http::recorded())
            ->filter(fn (array $pair): bool => $pair[0]->url() === self::VERIFY_URL)
            ->map(fn (array $pair): string => $pair[0]['requestId']);

        $this->assertCount(3, $requestIds);
        $this->assertCount(3, $requestIds->unique());
        $this->assertSame(1, UsageRecord::query()->count());
    }

    public function test_an_expired_gateway_token_is_renewed_once(): void
    {
        $this->fakeGateway([Http::response(['error' => 'invalid_token'], 401), $this->answer(200)], tokens: 2);

        $this->signedJson($this->license, 'POST', self::PATH, $this->validInput())
            ->assertOk()
            ->assertJsonPath('data.matched', true);

        Http::assertSentCount(4);
    }

    public function test_a_reference_makes_retries_idempotent(): void
    {
        $this->fakeGateway([$this->answer(200)]);

        $input = [...$this->validInput(), 'reference' => 'order-1001'];

        $this->signedJson($this->license, 'POST', self::PATH, $input)
            ->assertJsonPath('data.charged', true)
            ->assertJsonPath('data.replayed', false);

        $this->signedJson($this->license, 'POST', self::PATH, $input)
            ->assertOk()
            ->assertJsonPath('data.matched', true)
            ->assertJsonPath('data.charged', false)
            ->assertJsonPath('data.replayed', true);

        $this->assertSame(1, UsageRecord::query()->count());
        Http::assertSentCount(2);
    }

    public function test_invalid_input_is_rejected_before_calling_shahkar(): void
    {
        Http::fake();

        $this->signedJson($this->license, 'POST', self::PATH, ['national_code' => '1234567890', 'mobile' => '0912'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['national_code', 'mobile']]]);

        Http::assertNothingSent();
        $this->assertSame(0, UsageRecord::query()->count());
    }

    public function test_the_service_must_be_enabled_for_the_license(): void
    {
        Http::fake();
        $this->license->services()->detach();

        $this->signedJson($this->license, 'POST', self::PATH, $this->validInput())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'service_not_allowed');

        Http::assertNothingSent();
    }

    public function test_billable_requests_cannot_be_replayed(): void
    {
        $this->fakeGateway([$this->answer(200)]);
        $nonce = 'replay-nonce-0000000001';
        $timestamp = time();

        $this->signedJson($this->license, 'POST', self::PATH, $this->validInput(), $nonce, $timestamp)->assertOk();

        $this->signedJson($this->license, 'POST', self::PATH, $this->validInput(), $nonce, $timestamp)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'replayed_request');

        $this->assertSame(1, UsageRecord::query()->count());
    }

    public function test_inactive_licenses_cannot_use_paid_services(): void
    {
        Http::fake();
        $this->license->update(['expires_at' => now()->subDay()]);

        $this->signedJson($this->license, 'POST', self::PATH, $this->validInput())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'expired');

        Http::assertNothingSent();
    }

    public function test_missing_configuration_is_reported_without_billing(): void
    {
        Http::fake();
        Setting::set('shahkar.pid', null);

        $this->signedJson($this->license, 'POST', self::PATH, $this->validInput())
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'shahkar_configuration');

        Http::assertNothingSent();
        $this->assertFalse(UsageRecord::query()->sole()->billable);
    }

    /** @return array{national_code: string, mobile: string} */
    private function validInput(): array
    {
        return ['national_code' => self::NATIONAL_CODE, 'mobile' => '09123456789'];
    }

    /**
     * @param  list<mixed>  $verifyResponses
     */
    private function fakeGateway(array $verifyResponses, int $tokens = 1): void
    {
        $tokenResponses = Http::sequence();

        for ($index = 1; $index <= $tokens; $index++) {
            $tokenResponses->push(['access_token' => "token-{$index}", 'token_type' => 'bearer', 'expires_in' => 359]);
        }

        $verifySequence = Http::sequence();

        foreach ($verifyResponses as $response) {
            $verifySequence->pushResponse($response);
        }

        Http::fake([
            self::TOKEN_URL => $tokenResponses,
            self::VERIFY_URL => $verifySequence,
        ]);
    }

    private function answer(int $code): mixed
    {
        return Http::response([
            'result' => [
                'data' => [
                    'result' => [
                        'data' => [
                            'result' => 'OK.',
                            'requestId' => '050120260101120000000001',
                            'response' => $code,
                            'comment' => '...',
                        ],
                        'status' => ['statusCode' => 200, 'message' => 'OK'],
                    ],
                    'status' => ['statusCode' => 200, 'message' => 'OK'],
                ],
                'status' => ['statusCode' => 200],
            ],
            'status' => ['statusCode' => 200, 'message' => 'OK'],
        ], 200, ['requestId' => '050120260101120000000001']);
    }
}
