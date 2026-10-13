<?php

namespace App\Livewire\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\PaymentRecorder;
use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('پرداخت‌ها')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $filterCustomer = '';

    public bool $showForm = false;

    public string $customer_id = '';

    public string $invoice_id = '';

    public string $amount = '';

    public string $paid_on = '';

    public string $method = 'bank_transfer';

    public string $reference = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->paid_on = Jalali::date(today());

        if (request()->filled('customer')) {
            $this->customer_id = (string) request('customer');
        }

        if (request()->filled('invoice')) {
            $invoice = Invoice::query()->find(request('invoice'));

            if ($invoice !== null) {
                $this->invoice_id = (string) $invoice->id;
                $this->customer_id = (string) $invoice->customer_id;
                $this->amount = (string) max(0, $invoice->total - $invoice->paidAmount());
            }
        }

        $this->showForm = request()->boolean('create');
    }

    public function create(): void
    {
        $this->reset(['customer_id', 'invoice_id', 'amount', 'reference', 'notes']);
        $this->method = 'bank_transfer';
        $this->paid_on = Jalali::date(today());
        $this->resetValidation();
        $this->showForm = true;
    }

    public function updatedCustomerId(): void
    {
        $this->invoice_id = '';
    }

    public function updatedInvoiceId(string $value): void
    {
        $invoice = $value === '' ? null : Invoice::query()->find($value);

        if ($invoice !== null && $this->amount === '') {
            $this->amount = (string) max(0, $invoice->total - $invoice->paidAmount());
        }
    }

    public function save(PaymentRecorder $recorder): void
    {
        $this->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('customer_id', $this->customer_id)],
            'amount' => ['required'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['customer_id' => 'مشتری', 'amount' => 'مبلغ']);

        $amount = Money::parse($this->amount);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'مبلغ باید بیشتر از صفر باشد.']);
        }

        $paidOn = Jalali::parse($this->paid_on) ?? throw ValidationException::withMessages(['paid_on' => 'تاریخ را به شکل ۱۴۰۵/۰۱/۳۱ وارد کنید.']);
        $customer = Customer::withTrashed()->findOrFail($this->customer_id);
        $invoice = $this->invoice_id === '' ? null : Invoice::query()->find($this->invoice_id);

        $payment = $recorder->record($customer, $amount, $paidOn, PaymentMethod::from($this->method), $invoice, trim($this->reference) ?: null, trim($this->notes) ?: null);

        ActivityLog::record('payment.recorded', 'ثبت پرداخت '.Money::format($amount)." از «{$customer->display_name}»", $payment);
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'پرداخت ثبت شد.');
    }

    public function delete(Payment $payment, PaymentRecorder $recorder): void
    {
        $description = 'حذف پرداخت '.Money::format($payment->amount)." از «{$payment->customer?->display_name}»";
        $recorder->delete($payment);

        ActivityLog::record('payment.deleted', $description);
        $this->dispatch('toast', type: 'success', message: 'پرداخت حذف شد.');
    }

    public function render()
    {
        return view('livewire.payments.index', [
            'payments' => Payment::query()
                ->with(['customer', 'invoice'])
                ->when($this->filterCustomer !== '', fn ($query) => $query->where('customer_id', $this->filterCustomer))
                ->latest('paid_on')
                ->latest('id')
                ->paginate(25),
            'customers' => Customer::withTrashed()->orderBy('name')->get()->mapWithKeys(fn (Customer $customer) => [$customer->id => $customer->display_name]),
            'openInvoices' => $this->customer_id === '' ? collect() : Invoice::query()
                ->where('customer_id', $this->customer_id)
                ->where('status', InvoiceStatus::Issued)
                ->latest('id')
                ->get()
                ->mapWithKeys(fn (Invoice $invoice) => [$invoice->id => $invoice->number.' — '.Money::format($invoice->total)]),
            'methods' => PaymentMethod::options(),
        ]);
    }
}
