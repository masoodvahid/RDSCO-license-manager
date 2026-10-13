<?php

namespace Tests\Feature\Panel;

use App\Enums\UsageStatus;
use App\Models\AppError;
use App\Models\License;
use App\Models\Service;
use App\Models\UsageRecord;
use App\Services\Billing\InvoiceIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_panel_page_renders_with_data(): void
    {
        $this->actingAsAdmin();

        $license = License::factory()->create(['allowed_domains' => ['a.example.com'], 'features' => ['reports' => true]]);
        $license->services()->attach(Service::byCode(Service::SHAHKAR));

        UsageRecord::query()->create([
            'customer_id' => $license->customer_id,
            'license_id' => $license->id,
            'service_id' => Service::byCode(Service::SHAHKAR)->id,
            'status' => UsageStatus::Success,
            'billable' => true,
            'unit_price' => 5000,
            'upstream_code' => 200,
        ]);

        $invoice = app(InvoiceIssuer::class)->issue($license->customer);

        $error = AppError::query()->create([
            'license_id' => $license->id,
            'customer_id' => $license->customer_id,
            'product_id' => $license->product_id,
            'fingerprint' => str_repeat('a', 64),
            'level' => 'error',
            'message' => 'Something broke',
            'trace' => "#0 /app/x.php(1)\n#1 {main}",
            'context' => ['page' => 1],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $pages = [
            route('dashboard'),
            route('customers.index'),
            route('customers.create'),
            route('customers.show', $license->customer),
            route('customers.edit', $license->customer),
            route('products.index'),
            route('licenses.index'),
            route('licenses.create'),
            route('licenses.show', $license),
            route('licenses.edit', $license),
            route('services.index'),
            route('usage.index'),
            route('invoices.index'),
            route('invoices.create', ['customer' => $license->customer_id]),
            route('invoices.show', $invoice),
            route('invoices.print', $invoice),
            route('payments.index'),
            route('errors.index'),
            route('errors.show', $error),
            route('activity.index'),
            route('settings.general'),
            route('settings.shahkar'),
            route('profile'),
            route('docs'),
            route('system.update'),
        ];

        foreach ($pages as $url) {
            $response = $this->get($url);
            if ($response->status() !== 200 && $response->exception) {
                throw $response->exception;
            }
            $this->assertSame(200, $response->status(), "{$url} returned {$response->status()}");
        }
    }
}
