<?php

namespace App\Livewire\Errors;

use App\Enums\ErrorStatus;
use App\Models\AppError;
use App\Models\Customer;
use App\Models\Product;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('خطاهای سامانه‌ها')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $product = '';

    #[Url(except: '')]
    public string $customer = '';

    #[Url(except: '')]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function setStatus(AppError $error, string $status): void
    {
        $status = ErrorStatus::from($status);
        $error->update([
            'status' => $status,
            'resolved_at' => $status === ErrorStatus::Resolved ? now() : null,
        ]);

        $this->dispatch('toast', type: 'success', message: "وضعیت خطا «{$status->label()}» شد.");
    }

    public function render()
    {
        return view('livewire.errors.index', [
            'errors_' => AppError::query()
                ->with(['customer', 'product', 'license'])
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->when($this->product !== '', fn ($query) => $query->where('product_id', $this->product))
                ->when($this->customer !== '', fn ($query) => $query->where('customer_id', $this->customer))
                ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('message', 'like', '%'.trim($this->search).'%')
                    ->orWhere('exception_class', 'like', '%'.trim($this->search).'%')))
                ->latest('last_seen_at')
                ->paginate(30),
            'statuses' => ErrorStatus::options(),
            'products' => Product::query()->orderBy('name')->pluck('name', 'id'),
            'customers' => Customer::withTrashed()->orderBy('name')->get()->mapWithKeys(fn (Customer $customer) => [$customer->id => $customer->display_name]),
        ]);
    }
}
