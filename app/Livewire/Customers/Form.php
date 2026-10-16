<?php

namespace App\Livewire\Customers;

use App\Enums\ContactChannel;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Rules\IranianNationalCode;
use App\Support\Jalali;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?Customer $customer = null;

    public string $type = 'individual';

    public string $name = '';

    public string $company_name = '';

    public string $national_id = '';

    public string $registration_number = '';

    public string $mobile = '';

    public string $email = '';

    public string $website = '';

    /** @var list<string> */
    public array $contact_channels = [];

    public string $address = '';

    public string $postal_code = '';

    public string $status = 'active';

    public string $notes = '';

    public function mount(?Customer $customer = null): void
    {
        if ($customer?->exists) {
            $this->customer = $customer;
            $this->fill(array_map(fn ($value): string => (string) $value, $customer->only(['name', 'company_name', 'national_id', 'registration_number', 'mobile', 'email', 'website', 'address', 'postal_code', 'notes'])));
            $this->contact_channels = array_values((array) ($customer->contact_channels ?? []));
            $this->status = $customer->status->value;
            $this->type = $customer->type->value;
        }
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'website.regex' => 'آدرس سایت معتبر نیست؛ مثلاً example.com',
            'postal_code.digits' => 'کد پستی باید ۱۰ رقم باشد.',
            'national_id.digits' => 'شناسه ملی باید ۱۱ رقم باشد.',
        ];
    }

    public function save(): void
    {
        $this->website = trim($this->website);
        $this->postal_code = Jalali::toLatinDigits(trim($this->postal_code));
        $this->national_id = Jalali::toLatinDigits(trim($this->national_id));
        $isLegal = $this->type === CustomerType::Legal->value;

        $data = $this->validate([
            'type' => ['required', Rule::enum(CustomerType::class)],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'national_id' => $isLegal ? ['nullable', 'digits:11'] : ['nullable', new IranianNationalCode],
            'registration_number' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/)?[^\s\/$.?#][^\s]*\.[^\s]{2,}$/i'],
            'contact_channels' => ['array'],
            'contact_channels.*' => ['string', Rule::enum(ContactChannel::class)],
            'address' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'digits:10'],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);
        if (! $isLegal) {
            $data['registration_number'] = null;
        }

        $data['contact_channels'] = array_values(array_unique($this->contact_channels)) ?: null;

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
        return view('livewire.customers.form', [
            'statuses' => CustomerStatus::options(),
            'types' => CustomerType::options(),
            'nationalIdLabel' => (CustomerType::tryFrom($this->type) ?? CustomerType::Individual)->nationalIdLabel(),
            'channels' => ContactChannel::options(),
        ])
            ->title($this->customer ? 'ویرایش مشتری' : 'مشتری جدید');
    }
}
