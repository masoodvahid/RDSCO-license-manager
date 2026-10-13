<?php

namespace App\Livewire\Customers;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('مشتری‌ها')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $customers = Customer::query()
            ->search($this->search)
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->withCount('licenses')
            ->withSum(['invoices as invoiced_total' => fn ($query) => $query->where('status', '!=', InvoiceStatus::Void)], 'total')
            ->withSum('payments as paid_total', 'amount')
            ->latest('id')
            ->paginate(20);

        return view('livewire.customers.index', [
            'customers' => $customers,
            'statuses' => CustomerStatus::options(),
        ]);
    }
}
