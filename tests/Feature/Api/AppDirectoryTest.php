<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\V1\AppDirectoryController;
use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Services\Licensing\LicenseTokenIssuer;
use App\Services\Licensing\SigningKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/api/v1/app/resolve';

    private function fishinoLicense(array $attributes = []): License
    {
        $product = Product::query()->firstWhere('slug', 'fishino')
            ?? Product::factory()->create(['slug' => 'fishino']);

        return License::factory()->for($product)->create(['app_url' => 'https://hrm.payamnasr.com', ...$attributes]);
    }

    public function test_a_valid_code_returns_the_employer_and_a_signed_address(): void
    {
        $license = $this->fishinoLicense();
        $license->customer->update(['company_name' => 'شرکت پیام نصر']);

        $response = $this->postJson(self::PATH, [
            'code' => $license->code,
            'product' => 'fishino',
            'challenge' => 'Zx81kd0QpLm2Vb7n',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.employer.name', 'شرکت پیام نصر')
            ->assertJsonPath('data.employer.app_url', 'https://hrm.payamnasr.com')
            ->assertJsonPath('data.employer.api_base_url', 'https://hrm.payamnasr.com/api/v1');

        $claims = LicenseTokenIssuer::verify($response->json('data.token'), app(SigningKey::class)->publicKey());

        $this->assertNotNull($claims);
        $this->assertSame(AppDirectoryController::TOKEN_TYPE, $claims['typ']);
        $this->assertSame($license->code, $claims['code']);
        $this->assertSame('fishino', $claims['prd']);
        $this->assertSame('https://hrm.payamnasr.com/api/v1', $claims['url']);
        $this->assertSame('Zx81kd0QpLm2Vb7n', $claims['chl']);
    }

    public function test_persian_digits_are_accepted(): void
    {
        $license = $this->fishinoLicense();
        $persian = strtr($license->code, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        $this->postJson(self::PATH, ['code' => $persian, 'product' => 'fishino'])->assertOk();
    }

    public function test_unknown_codes_and_codes_of_other_products_are_rejected(): void
    {
        $license = $this->fishinoLicense();

        $this->postJson(self::PATH, ['code' => $license->code === '111111' ? '222222' : '111111', 'product' => 'fishino'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'code_not_found');

        $this->postJson(self::PATH, ['code' => $license->code, 'product' => 'tooka'])
            ->assertNotFound();
    }

    public function test_licenses_without_an_app_address_are_not_resolvable(): void
    {
        $license = $this->fishinoLicense(['app_url' => null]);

        $this->postJson(self::PATH, ['code' => $license->code, 'product' => 'fishino'])->assertNotFound();
    }

    public function test_an_address_saved_without_scheme_is_returned_as_https(): void
    {
        $license = $this->fishinoLicense(['app_url' => 'hrm.demo.fishino.ir']);

        $this->postJson(self::PATH, ['code' => $license->code, 'product' => 'fishino'])
            ->assertOk()
            ->assertJsonPath('data.employer.api_base_url', 'https://hrm.demo.fishino.ir/api/v1');
    }

    public function test_an_api_path_saved_in_the_address_is_not_doubled(): void
    {
        $license = $this->fishinoLicense(['app_url' => 'https://hrm.payamnasr.com/api/v1/']);

        $this->postJson(self::PATH, ['code' => $license->code, 'product' => 'fishino'])
            ->assertOk()
            ->assertJsonPath('data.employer.api_base_url', 'https://hrm.payamnasr.com/api/v1');
    }

    public function test_an_old_http_address_is_refused(): void
    {
        $license = $this->fishinoLicense(['app_url' => 'http://hrm.payamnasr.com']);

        $this->postJson(self::PATH, ['code' => $license->code, 'product' => 'fishino'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'employer_misconfigured')
            ->assertJsonMissingPath('data');
    }

    public function test_malformed_codes_fail_validation(): void
    {
        $this->postJson(self::PATH, ['code' => '12ab56', 'product' => 'fishino'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_inactive_employers_are_refused_without_giving_the_address(): void
    {
        foreach ([
            $this->fishinoLicense(['status' => 'suspended']),
            $this->fishinoLicense(['expires_at' => now()->subDay()->toDateString()]),
            $this->fishinoLicense(['customer_id' => Customer::factory()->suspended()]),
        ] as $license) {
            $this->postJson(self::PATH, ['code' => $license->code, 'product' => 'fishino'])
                ->assertForbidden()
                ->assertJsonPath('error.code', 'employer_inactive')
                ->assertJsonMissingPath('data');
        }
    }

    public function test_repeated_wrong_codes_lock_the_ip_but_successful_lookups_do_not_count(): void
    {
        config(['license.rate_limits.app_code_failures_per_minute' => 3]);
        $license = $this->fishinoLicense();
        $wrong = $license->code === '999999' ? '999998' : '999999';

        foreach (range(1, 5) as $i) {
            $this->postJson(self::PATH, ['code' => $license->code, 'product' => 'fishino'])->assertOk();
        }

        foreach (range(1, 3) as $i) {
            $this->postJson(self::PATH, ['code' => $wrong, 'product' => 'fishino'])->assertNotFound();
        }

        $this->postJson(self::PATH, ['code' => $license->code, 'product' => 'fishino'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limited')
            ->assertHeader('Retry-After');
    }
}
