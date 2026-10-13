<?php

namespace App\Livewire\Services;

use App\Models\ActivityLog;
use App\Models\Service;
use App\Support\Money;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('سرویس‌ها و تعرفه')]
class Index extends Component
{
    /** @var array<int, array{name: string, unit_price: string, is_active: bool}> */
    public array $form = [];

    public function mount(): void
    {
        $this->form = Service::query()->orderBy('id')->get()
            ->mapWithKeys(fn (Service $service): array => [$service->id => [
                'name' => $service->name,
                'unit_price' => (string) $service->unit_price,
                'is_active' => $service->is_active,
            ]])
            ->all();
    }

    public function save(): void
    {
        $this->validate([
            'form.*.name' => ['required', 'string', 'max:255'],
            'form.*.unit_price' => ['required'],
            'form.*.is_active' => ['boolean'],
        ]);

        foreach ($this->form as $id => $values) {
            $service = Service::query()->findOrFail($id);
            $service->update([
                'name' => $values['name'],
                'unit_price' => Money::parse($values['unit_price']),
                'is_active' => (bool) $values['is_active'],
            ]);
        }

        ActivityLog::record('service.updated', 'تغییر تعرفه پیش‌فرض سرویس‌ها');
        $this->dispatch('toast', type: 'success', message: 'تعرفه‌ها ذخیره شد. درخواست‌های بعدی با قیمت جدید ثبت می‌شوند؛ مصرف قبلی تغییر نمی‌کند.');
    }

    public function render()
    {
        return view('livewire.services.index', [
            'services' => Service::query()->withCount(['customerPrices', 'licenses'])->orderBy('id')->get(),
        ]);
    }
}
