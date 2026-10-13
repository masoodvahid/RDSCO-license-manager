<?php

namespace App\Livewire\Settings;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\Licensing\SigningKey;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('تنظیمات')]
class General extends Component
{
    public const FIELDS = [
        'company.name' => 'نام فروشنده',
        'company.national_id' => 'شناسه ملی',
        'company.economic_code' => 'کد اقتصادی',
        'company.phone' => 'تلفن',
        'company.address' => 'نشانی',
        'company.payment_note' => 'توضیح پرداخت در فاکتور',
        'billing.tax_rate' => 'نرخ مالیات بر ارزش افزوده (٪)',
    ];

    /** @var array<string, string> */
    public array $settings = [];

    public function mount(): void
    {
        foreach (array_keys(self::FIELDS) as $key) {
            $this->settings[str_replace('.', '_', $key)] = (string) Setting::get($key, '');
        }
    }

    public function save(): void
    {
        $this->validate([
            'settings.company_name' => ['nullable', 'string', 'max:255'],
            'settings.company_national_id' => ['nullable', 'string', 'max:20'],
            'settings.company_economic_code' => ['nullable', 'string', 'max:20'],
            'settings.company_phone' => ['nullable', 'string', 'max:50'],
            'settings.company_address' => ['nullable', 'string', 'max:500'],
            'settings.company_payment_note' => ['nullable', 'string', 'max:1000'],
            'settings.billing_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], attributes: ['settings.billing_tax_rate' => 'نرخ مالیات']);

        foreach (array_keys(self::FIELDS) as $key) {
            Setting::set($key, trim($this->settings[str_replace('.', '_', $key)] ?? ''));
        }

        ActivityLog::record('settings.updated', 'ذخیره تنظیمات عمومی');
        $this->dispatch('toast', type: 'success', message: 'تنظیمات ذخیره شد.');
    }

    public function regenerateSigningKey(SigningKey $signingKey): void
    {
        $signingKey->generate();

        ActivityLog::record('settings.signing_key', 'ساخت کلید امضای جدید لایسنس‌ها');
        $this->dispatch('toast', type: 'warning', message: 'کلید امضای جدید ساخته شد. کلید عمومی جدید را در همه برنامه‌ها جایگزین کنید.');
    }

    public function render(SigningKey $signingKey)
    {
        return view('livewire.settings.general', [
            'publicKey' => $signingKey->publicKey(),
            'keyId' => $signingKey->keyId(),
            'graceHours' => (int) config('license.offline_grace_hours'),
        ]);
    }
}
