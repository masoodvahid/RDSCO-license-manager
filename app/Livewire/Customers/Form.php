<?php

namespace App\Livewire\Customers;

use App\Enums\CustomerStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?Customer $customer = null;

    public string $name = '';

    public string $company_name = '';

    public string $national_id = '';

    public string $economic_code = '';

    public string $mobile = '';

    public string $email = '';

    public string $address = '';

    public string $status = 'active';

    public string $notes = '';

    public function mount(?Customer $customer = null): void
    {
        if ($customer?->exists) {
            $this->customer = $customer;
            $this->fill(array_map(fn ($value): string => (string) $value, $customer->only(['name', 'company_name', 'national_id', 'economic_code', 'mobile', 'email', 'address', 'notes'])));
            $this->status = $customer->status->value;
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'economic_code' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);

        if ($this->customer === null) {
            $customer = Customer::query()->create($data);
            ActivityLog::record('customer.created', "ثبت مشتری «{$customer->display_name}»", $customer);
        } else {
            $customer = $this->customer;
            $customer->update($data);
            ActivityLog::record('customer.updated', "ویرایش مشتری «{$customer->display_name}»", $customer);
        }

        session()->flash('toast', ['type' => 'success', 'message' => 'اطلاعات مشتری ذخیره شد.']);
        $this->redirectRoute('customers.show', $customer, navigate: true);
    }

    public function render()
    {
        return view('livewire.customers.form', ['statuses' => CustomerStatus::options()])
            ->title($this->customer ? 'ویرایش مشتری' : 'مشتری جدید');
    }
}
