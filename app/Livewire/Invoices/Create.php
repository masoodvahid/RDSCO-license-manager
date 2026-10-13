<?php

namespace App\Livewire\Invoices;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\UsageRecord;
use App\Services\Billing\InvoiceIssuer;
use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

#[Title('صدور فاکتور')]
class Create extends Component
{
    #[Url(as: 'customer')]
    public string $customer_id = '';

    public string $until = '';

    public string $discount = '';

    public string $notes = '';

    public function issue(InvoiceIssuer $issuer): void
    {
        $customer = $this->selectedCustomer() ?? throw ValidationException::withMessages(['customer_id' => 'مشتری را انتخاب کنید.']);
        $until = $this->untilDate();

        try {
            $invoice = $issuer->issue($customer, Money::parse($this->discount), trim($this->notes) ?: null, $until);
        } catch (RuntimeException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());

            return;
        }

        ActivityLog::record('invoice.issued', "صدور فاکتور {$invoice->number} برای «{$customer->display_name}»", $invoice);
        session()->flash('toast', ['type' => 'success', 'message' => "فاکتور {$invoice->number} صادر شد."]);
        $this->redirectRoute('invoices.show', $invoice, navigate: true);
    }

    private function selectedCustomer(): ?Customer
    {
        return $this->customer_id === '' ? null : Customer::withTrashed()->find($this->customer_id);
    }

    private function untilDate(): ?Carbon
    {
        if (trim($this->until) === '') {
            return null;
        }

        return Jalali::parse($this->until)?->endOfDay()
            ?? throw ValidationException::withMessages(['until' => 'تاریخ را به شکل ۱۴۰۵/۰۱/۳۱ وارد کنید.']);
    }

    public function render(InvoiceIssuer $issuer)
    {
        $customer = $this->selectedCustomer();
        $until = Jalali::parse($this->until)?->endOfDay();
        $preview = $customer ? $issuer->preview($customer, $until) : null;
        $discount = min(Money::parse($this->discount), $preview['subtotal'] ?? 0);
        $taxRate = (float) Setting::get('billing.tax_rate', 0);
        $taxable = ($preview['subtotal'] ?? 0) - $discount;

        $customersWithUsage = UsageRecord::query()
            ->whereNull('invoice_id')
            ->where('billable', true)
            ->selectRaw('customer_id, SUM(unit_price) AS amount, COUNT(*) AS requests')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        return view('livewire.invoices.create', [
            'customers' => Customer::withTrashed()->orderBy('name')->get()->mapWithKeys(fn (Customer $customer) => [
                $customer->id => $customer->display_name.(isset($customersWithUsage[$customer->id]) ? ' — '.Money::format((int) $customersWithUsage[$customer->id]->amount) : ''),
            ]),
            'pending' => $customersWithUsage,
            'customer' => $customer,
            'preview' => $preview,
            'discountAmount' => $discount,
            'taxRate' => $taxRate,
            'taxAmount' => (int) round($taxable * $taxRate / 100),
            'total' => $taxable + (int) round($taxable * $taxRate / 100),
        ]);
    }
}
