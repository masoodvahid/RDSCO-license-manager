<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\UsageRecord;
use App\Support\Jalali;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns uninvoiced billable usage into an invoice, at any moment.
 *
 * Usage rows are claimed by setting their invoice_id first and the invoice
 * lines are then computed from exactly the claimed rows, so a row is never
 * billed twice or missed, even while new usage keeps arriving.
 */
class InvoiceIssuer
{
    /**
     * Summary of what would be invoiced now.
     *
     * @return array{count: int, subtotal: int, from: ?Carbon, to: ?Carbon, lines: list<array{service_id: int, service: string, quantity: int, unit_price: int, amount: int}>}
     */
    public function preview(Customer $customer, ?Carbon $until = null): array
    {
        $rows = $this->uninvoiced($customer, $until)
            ->selectRaw('service_id, unit_price, COUNT(*) AS quantity, MIN(created_at) AS first_at, MAX(created_at) AS last_at')
            ->groupBy('service_id', 'unit_price')
            ->get();

        return $this->summarize($rows);
    }

    public function issue(Customer $customer, int $discount = 0, ?string $notes = null, ?Carbon $until = null): Invoice
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): Invoice => $this->createInvoice($customer, $discount, $notes, $until));
            } catch (QueryException $exception) {
                // Invoice number collision with a concurrent issue: retry with the next number.
                if ($attempt >= 3 || ! str_contains(strtolower($exception->getMessage()), 'unique')) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Cancel an invoice and release its usage so it can be invoiced again.
     */
    public function void(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            UsageRecord::query()->where('invoice_id', $invoice->getKey())->update(['invoice_id' => null]);

            $invoice->forceFill([
                'status' => InvoiceStatus::Void,
                'voided_at' => now(),
            ])->save();
        });
    }

    private function createInvoice(Customer $customer, int $discount, ?string $notes, ?Carbon $until): Invoice
    {
        // Serialize invoicing per customer.
        Customer::query()->whereKey($customer->getKey())->lockForUpdate()->first();

        $maxId = $this->uninvoiced($customer, $until)->max('id');

        if ($maxId === null) {
            throw new RuntimeException('مصرف فاکتورنشده‌ای برای این مشتری وجود ندارد.');
        }

        $now = now();
        $invoice = Invoice::query()->create([
            'number' => $this->nextNumber(),
            'customer_id' => $customer->getKey(),
            'status' => InvoiceStatus::Issued,
            'period_end' => $until ?? $now,
            'issued_at' => $now,
            'notes' => $notes,
        ]);

        $this->uninvoiced($customer, $until)
            ->where('id', '<=', $maxId)
            ->update(['invoice_id' => $invoice->getKey()]);

        $rows = UsageRecord::query()
            ->where('invoice_id', $invoice->getKey())
            ->selectRaw('service_id, unit_price, COUNT(*) AS quantity, MIN(created_at) AS first_at, MAX(created_at) AS last_at')
            ->groupBy('service_id', 'unit_price')
            ->get();

        $summary = $this->summarize($rows);

        foreach ($summary['lines'] as $line) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->getKey(),
                'service_id' => $line['service_id'],
                'description' => $line['service'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'amount' => $line['amount'],
            ]);
        }

        $subtotal = $summary['subtotal'];
        $discount = max(0, min($discount, $subtotal));
        $taxRate = max(0.0, (float) Setting::get('billing.tax_rate', 0));
        $taxAmount = (int) round(($subtotal - $discount) * $taxRate / 100);

        $invoice->forceFill([
            'period_start' => $summary['from'],
            'period_end' => $summary['to'] ?? $invoice->period_end,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $subtotal - $discount + $taxAmount,
        ])->save();

        return $invoice->refresh();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<UsageRecord>
     */
    private function uninvoiced(Customer $customer, ?Carbon $until)
    {
        return UsageRecord::query()
            ->where('customer_id', $customer->getKey())
            ->whereNull('invoice_id')
            ->where('billable', true)
            ->when($until !== null, fn ($query) => $query->where('created_at', '<=', $until));
    }

    /**
     * @param  iterable<object>  $rows
     * @return array{count: int, subtotal: int, from: ?Carbon, to: ?Carbon, lines: list<array{service_id: int, service: string, quantity: int, unit_price: int, amount: int}>}
     */
    private function summarize(iterable $rows): array
    {
        $serviceNames = Service::query()->pluck('name', 'id');
        $lines = [];
        $count = 0;
        $subtotal = 0;
        $from = null;
        $to = null;

        foreach ($rows as $row) {
            $quantity = (int) $row->quantity;
            $unitPrice = (int) $row->unit_price;
            $amount = $quantity * $unitPrice;

            $lines[] = [
                'service_id' => (int) $row->service_id,
                'service' => (string) ($serviceNames[$row->service_id] ?? 'سرویس'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ];

            $count += $quantity;
            $subtotal += $amount;

            $firstAt = Carbon::parse($row->first_at);
            $lastAt = Carbon::parse($row->last_at);
            $from = $from === null || $firstAt->lt($from) ? $firstAt : $from;
            $to = $to === null || $lastAt->gt($to) ? $lastAt : $to;
        }

        return compact('count', 'subtotal', 'from', 'to', 'lines');
    }

    /**
     * Sequential per Jalali year: 1405-0001, 1405-0002, ...
     */
    private function nextNumber(): string
    {
        $prefix = Jalali::currentYear().'-';

        $last = Invoice::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 1 : ((int) substr((string) $last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
