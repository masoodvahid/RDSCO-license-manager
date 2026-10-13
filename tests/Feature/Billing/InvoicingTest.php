<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\UsageStatus;
use App\Models\Customer;
use App\Models\License;
use App\Models\Service;
use App\Models\Setting;
use App\Models\UsageRecord;
use App\Services\Billing\InvoiceIssuer;
use App\Services\Billing\PaymentRecorder;
use App\Support\Jalali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InvoicingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invoice_bills_exactly_the_uninvoiced_billable_usage(): void
    {
        $license = License::factory()->create();
        $customer = $license->customer;
        $other = License::factory()->create();

        $this->usage($license, 3, price: 10_000);
        $this->usage($license, 2, price: 12_000);          // price changed mid-period
        $this->usage($license, 4, price: 0, billable: false); // errors are free
        $this->usage($other, 5, price: 10_000);            // another customer

        $issuer = app(InvoiceIssuer::class);
        $preview = $issuer->preview($customer);
        $this->assertSame(5, $preview['count']);
        $this->assertSame(54_000, $preview['subtotal']);

        Setting::set('billing.tax_rate', 10);
        $invoice = $issuer->issue($customer, discount: 4_000);

        $this->assertSame(Jalali::currentYear().'-0001', $invoice->number);
        $this->assertSame(54_000, $invoice->subtotal);
        $this->assertSame(4_000, $invoice->discount);
        $this->assertSame(5_000, $invoice->tax_amount);
        $this->assertSame(55_000, $invoice->total);
        $this->assertCount(2, $invoice->items);
        $this->assertSame(5, UsageRecord::query()->where('invoice_id', $invoice->id)->count());

        // Nothing left to bill for this customer; the other customer is untouched.
        $this->assertSame(0, $issuer->preview($customer)['count']);
        $this->assertSame(5, $issuer->preview($other->customer)['count']);

        $this->expectException(RuntimeException::class);
        $issuer->issue($customer);
    }

    public function test_new_usage_after_an_invoice_goes_to_the_next_one(): void
    {
        $license = License::factory()->create();
        $issuer = app(InvoiceIssuer::class);

        $this->usage($license, 2, price: 1_000);
        $first = $issuer->issue($license->customer);

        $this->usage($license, 3, price: 1_000);
        $second = $issuer->issue($license->customer);

        $this->assertSame(2_000, $first->total);
        $this->assertSame(3_000, $second->total);
        $this->assertSame(Jalali::currentYear().'-0002', $second->number);
    }

    public function test_voiding_an_invoice_releases_its_usage(): void
    {
        $license = License::factory()->create();
        $issuer = app(InvoiceIssuer::class);
        $this->usage($license, 3, price: 1_000);

        $invoice = $issuer->issue($license->customer);
        $issuer->void($invoice);

        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
        $this->assertSame(3, $issuer->preview($license->customer)['count']);
        $this->assertSame(0, $license->customer->balance());
    }

    public function test_payments_settle_invoices_and_the_balance(): void
    {
        $license = License::factory()->create();
        $customer = $license->customer;
        $this->usage($license, 10, price: 1_000);
        $invoice = app(InvoiceIssuer::class)->issue($customer);
        $payments = app(PaymentRecorder::class);

        $this->assertSame(10_000, $customer->balance());

        $first = $payments->record($customer, 4_000, now(), PaymentMethod::BankTransfer, $invoice);
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);

        $payments->record($customer, 6_000, now(), PaymentMethod::Card, $invoice);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(0, $customer->balance());

        $payments->delete($first);
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
        $this->assertSame(4_000, $customer->balance());
    }

    private function usage(License $license, int $count, int $price, bool $billable = true): void
    {
        $serviceId = Service::byCode(Service::SHAHKAR)->id;

        for ($index = 0; $index < $count; $index++) {
            UsageRecord::query()->create([
                'customer_id' => $license->customer_id,
                'license_id' => $license->id,
                'service_id' => $serviceId,
                'status' => $billable ? UsageStatus::Success : UsageStatus::Error,
                'billable' => $billable,
                'unit_price' => $price,
            ]);
        }
    }
}
