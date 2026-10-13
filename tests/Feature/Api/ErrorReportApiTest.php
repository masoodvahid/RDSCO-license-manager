<?php

namespace Tests\Feature\Api;

use App\Enums\ErrorStatus;
use App\Models\AppError;
use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorReportApiTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/api/v1/errors';

    public function test_errors_are_stored_and_grouped_by_fingerprint(): void
    {
        $license = License::factory()->create();

        $report = fn (int $userId): array => [
            'message' => "User {$userId} not found",
            'exception_class' => 'App\\Exceptions\\UserNotFound',
            'file' => '/var/www/app/Services/Users.php',
            'line' => 42,
            'level' => 'error',
            'app_version' => '1.2.0',
            'context' => ['user_id' => $userId, 'password' => 'hunter2', 'nested' => ['api_key' => 'abc', 'page' => 3]],
        ];

        $this->signedJson($license, 'POST', self::PATH, $report(15))
            ->assertCreated()
            ->assertJsonPath('data.occurrences', 1);

        $this->signedJson($license, 'POST', self::PATH, $report(16))
            ->assertCreated()
            ->assertJsonPath('data.occurrences', 2);

        $error = AppError::query()->sole();
        $this->assertSame(2, $error->occurrences);
        $this->assertSame('User 16 not found', $error->message);
        $this->assertSame('[REDACTED]', $error->context['password']);
        $this->assertSame('[REDACTED]', $error->context['nested']['api_key']);
        $this->assertSame(3, $error->context['nested']['page']);
        $this->assertSame($license->customer_id, $error->customer_id);
        $this->assertSame($license->product_id, $error->product_id);
    }

    public function test_a_resolved_error_reopens_but_an_ignored_one_stays_ignored(): void
    {
        $license = License::factory()->create();
        $report = ['message' => 'Boom', 'exception_class' => 'RuntimeException'];

        $this->signedJson($license, 'POST', self::PATH, $report)->assertCreated();
        $error = AppError::query()->sole();

        $error->update(['status' => ErrorStatus::Resolved, 'resolved_at' => now()]);
        $this->signedJson($license, 'POST', self::PATH, $report)->assertCreated();
        $this->assertSame(ErrorStatus::Open, $error->fresh()->status);

        $error->update(['status' => ErrorStatus::Ignored]);
        $this->signedJson($license, 'POST', self::PATH, $report)->assertCreated();
        $this->assertSame(ErrorStatus::Ignored, $error->fresh()->status);
        $this->assertSame(3, $error->fresh()->occurrences);
    }

    public function test_reports_require_a_message_and_a_fresh_nonce(): void
    {
        $license = License::factory()->create();

        $this->signedJson($license, 'POST', self::PATH, ['level' => 'error'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $nonce = 'nonce-for-error-report-1';
        $this->signedJson($license, 'POST', self::PATH, ['message' => 'x'], $nonce)->assertCreated();
        $this->signedJson($license, 'POST', self::PATH, ['message' => 'x'], $nonce)->assertStatus(409);
    }

    public function test_oversized_bodies_are_rejected(): void
    {
        config(['license.max_body_bytes' => 1024]);
        $license = License::factory()->create();

        $this->signedJson($license, 'POST', self::PATH, ['message' => str_repeat('a', 2000)])
            ->assertStatus(413)
            ->assertJsonPath('error.code', 'payload_too_large');
    }
}
