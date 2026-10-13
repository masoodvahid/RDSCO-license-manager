<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('فاکتورها')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $customer = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.invoices.index', [
            'invoices' => Invoice::query()
                ->with('customer')
                ->withSum('payments', 'amount')
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->when($this->customer !== '', fn ($query) => $query->where('customer_id', $this->customer))
                ->latest('id')
                ->paginate(25),
            'statuses' => InvoiceStatus::options(),
            'customers' => Customer::withTrashed()->orderBy('name')->get()->mapWithKeys(fn (Customer $customer) => [$customer->id => $customer->display_name]),
        ]);
    }
}
