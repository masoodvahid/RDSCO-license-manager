<?php

namespace App\Livewire\Customers;

use App\Enums\ContactChannel;
use App\Enums\InvoiceStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerServicePrice;
use App\Models\Service;
use App\Services\Billing\InvoiceIssuer;
use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Show extends Component
{
    public Customer $customer;

    /** @var array<int, string> service id => custom price (empty = default) */
    public array $prices = [];

    public string $contactDate = '';

    public string $contactChannel = '';

    public string $contactSummary = '';

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
        $this->contactDate = Jalali::date(today());
        $this->loadPrices();
    }

    public function addContact(): void
    {
        $this->validate([
            'contactDate' => ['required', 'string'],
            'contactChannel' => ['nullable', Rule::enum(ContactChannel::class)],
            'contactSummary' => ['required', 'string', 'max:5000'],
        ], [
            'contactSummary.required' => 'شرح گفتگو را بنویسید.',
        ]);

        $contactedOn = Jalali::parse($this->contactDate)
            ?? throw ValidationException::withMessages(['contactDate' => 'تاریخ را به شکل ۱۴۰۵/۰۱/۳۱ وارد کنید.']);

        $this->customer->contacts()->create([
            'contacted_on' => $contactedOn,
            'channel' => $this->contactChannel === '' ? null : $this->contactChannel,
            'summary' => trim($this->contactSummary),
        ]);

        $this->reset('contactChannel', 'contactSummary');
        $this->contactDate = Jalali::date(today());
        $this->resetErrorBag();
        $this->dispatch('toast', type: 'success', message: 'گزارش تماس ثبت شد.');
    }

    public function deleteContact(int $id): void
    {
        $this->customer->contacts()->whereKey($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'گزارش تماس حذف شد.');
    }

    public function savePrices(): void
    {
        foreach (Service::query()->get() as $service) {
            $value = trim((string) ($this->prices[$service->id] ?? ''));

            if ($value === '') {
                CustomerServicePrice::query()->where('customer_id', $this->customer->id)->where('service_id', $service->id)->delete();

                continue;
            }

            CustomerServicePrice::query()->updateOrCreate(
                ['customer_id' => $this->customer->id, 'service_id' => $service->id],
                ['unit_price' => Money::parse($value)],
            );
        }

        ActivityLog::record('customer.prices', "تغییر تعرفه اختصاصی «{$this->customer->display_name}»", $this->customer);
        $this->loadPrices();
        $this->dispatch('toast', type: 'success', message: 'تعرفه‌های اختصاصی ذخیره شد. درخواست‌های بعدی با این قیمت ثبت می‌شوند.');
    }

    public function delete(): void
    {
        $name = $this->customer->display_name;
        $this->customer->delete();

        ActivityLog::record('customer.deleted', "حذف مشتری «{$name}»", $this->customer);
        session()->flash('toast', ['type' => 'success', 'message' => 'مشتری حذف شد و لایسنس‌های او غیرفعال شدند.']);
        $this->redirectRoute('customers.index', navigate: true);
    }

    private function loadPrices(): void
    {
        $custom = $this->customer->servicePrices()->pluck('unit_price', 'service_id');

        $this->prices = Service::query()->pluck('id')
            ->mapWithKeys(fn (int $id): array => [$id => isset($custom[$id]) ? (string) $custom[$id] : ''])
            ->all();
    }

    public function render()
    {
        $invoiced = (int) $this->customer->invoices()->where('status', '!=', InvoiceStatus::Void)->sum('total');
        $paid = (int) $this->customer->payments()->sum('amount');

        return view('livewire.customers.show', [
            'licenses' => $this->customer->licenses()->with('product')->latest('id')->get(),
            'invoices' => $this->customer->invoices()->latest('id')->limit(8)->get(),
            'payments' => $this->customer->payments()->latest('paid_on')->limit(8)->get(),
            'services' => Service::query()->orderBy('id')->get(),
            'contacts' => $this->customer->contacts()->orderByDesc('contacted_on')->orderByDesc('id')->get(),
            'channelOptions' => ContactChannel::options(),
            'preview' => app(InvoiceIssuer::class)->preview($this->customer),
            'invoiced' => $invoiced,
            'paid' => $paid,
        ])->title($this->customer->display_name);
    }
}
