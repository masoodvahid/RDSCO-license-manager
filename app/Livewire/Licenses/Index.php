<?php

namespace App\Livewire\Licenses;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Product;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('لایسنس‌ها')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** active | suspended | revoked | expired | expiring */
    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $product = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'product'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $licenses = License::query()
            ->with(['customer', 'product'])
            ->search($this->search)
            ->when($this->product !== '', fn ($query) => $query->where('product_id', $this->product))
            ->when($this->status === 'expired', fn ($query) => $query->where('expires_at', '<', today()))
            ->when($this->status === 'expiring', fn ($query) => $query->where('status', LicenseStatus::Active)->whereBetween('expires_at', [today(), today()->addDays(30)]))
            ->when(in_array($this->status, ['active', 'suspended', 'revoked'], true), fn ($query) => $query->where('status', $this->status))
            ->latest('id')
            ->paginate(25);

        return view('livewire.licenses.index', [
            'licenses' => $licenses,
            'products' => Product::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => [...LicenseStatus::options(), 'expiring' => 'در آستانه انقضا', 'expired' => 'منقضی'],
        ]);
    }
}
