<?php

namespace App\Livewire\Licenses;

use App\Enums\LicenseStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\Service;
use App\Services\Licensing\LicensePolicy;
use App\Support\Jalali;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\IpUtils;

class Form extends Component
{
    public ?License $license = null;

    #[Url(as: 'customer')]
    public string $customer_id = '';

    public string $product_id = '';

    public string $label = '';

    public string $app_url = '';

    public string $allowed_domains = '';

    public string $allowed_ips = '';

    public string $max_users = '';

    public string $features = '';

    public string $starts_at = '';

    public string $expires_at = '';

    public string $status = 'active';

    public string $notes = '';

    /** @var list<int|string> */
    public array $services = [];

    public function mount(?License $license = null): void
    {
        if ($license?->exists) {
            $this->license = $license;
            $this->customer_id = (string) $license->customer_id;
            $this->product_id = (string) $license->product_id;
            $this->label = (string) $license->label;
            $this->app_url = (string) $license->app_url;
            $this->allowed_domains = implode("\n", $license->allowed_domains ?? []);
            $this->allowed_ips = implode("\n", $license->allowed_ips ?? []);
            $this->max_users = (string) ($license->max_users ?? '');
            $this->features = self::featuresToText($license->features ?? []);
            $this->starts_at = $license->starts_at ? Jalali::date($license->starts_at) : '';
            $this->expires_at = $license->expires_at ? Jalali::date($license->expires_at) : '';
            $this->status = $license->status->value;
            $this->notes = (string) $license->notes;
            $this->services = $license->services()->pluck('services.id')->map(fn ($id) => (string) $id)->all();

            return;
        }

        $this->starts_at = Jalali::date(today());
        $this->expires_at = Jalali::date(today()->addYear()->subDay());
    }

    public function save(): void
    {
        $this->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'product_id' => ['required', Rule::exists('products', 'id')],
            'label' => ['nullable', 'string', 'max:255'],
            'app_url' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/)?[^\s\/$.?#][^\s]*\.[^\s]{2,}$/i'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'status' => ['required', Rule::enum(LicenseStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'services' => ['array'],
            'services.*' => [Rule::exists('services', 'id')],
            'allowed_domains' => ['nullable', 'string', 'max:5000'],
            'allowed_ips' => ['nullable', 'string', 'max:5000'],
            'features' => ['nullable', 'string', 'max:5000'],
        ], [
            'app_url.regex' => 'آدرس معتبر نیست؛ مثلاً https://fishino.example.com',
        ], [
            'customer_id' => 'مشتری',
            'product_id' => 'محصول',
            'max_users' => 'تعداد کاربر',
        ]);

        $startsAt = $this->parseDate('starts_at');
        $expiresAt = $this->parseDate('expires_at');

        if ($startsAt !== null && $expiresAt !== null && $expiresAt->lt($startsAt)) {
            throw ValidationException::withMessages(['expires_at' => 'تاریخ انقضا نباید قبل از تاریخ شروع باشد.']);
        }

        $data = [
            'customer_id' => (int) $this->customer_id,
            'product_id' => (int) $this->product_id,
            'label' => trim($this->label) ?: null,
            'app_url' => rtrim(trim($this->app_url), '/') ?: null,
            'allowed_domains' => $this->domains(),
            'allowed_ips' => $this->ips(),
            'max_users' => $this->max_users === '' ? null : (int) $this->max_users,
            'features' => $this->parseFeatures(),
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => $this->status,
            'notes' => trim($this->notes) ?: null,
        ];

        if ($this->license === null) {
            $license = License::query()->create($data);
            $secret = $license->secret;
            $license->services()->sync($this->services);
            $license->forgetCache();

            ActivityLog::record('license.created', "صدور لایسنس {$license->key_id}", $license);
            session()->flash('license_secret', $secret);
            session()->flash('toast', ['type' => 'success', 'message' => 'لایسنس صادر شد. کلید محرمانه را همین حالا ذخیره کنید.']);
        } else {
            $license = $this->license;
            $license->update($data);
            $license->services()->sync($this->services);
            $license->forgetCache();

            ActivityLog::record('license.updated', "ویرایش لایسنس {$license->key_id}", $license);
            session()->flash('toast', ['type' => 'success', 'message' => 'تغییرات لایسنس ذخیره شد و بلافاصله اعمال می‌شود.']);
        }

        $this->redirectRoute('licenses.show', $license, navigate: true);
    }

    /**
     * @return list<string>|null
     */
    private function domains(): ?array
    {
        $domains = [];

        foreach ($this->lines($this->allowed_domains) as $line) {
            $line = strtolower($line);
            $wildcard = str_starts_with($line, '*.');
            $host = LicensePolicy::normalizeHost($wildcard ? substr($line, 2) : $line);

            if ($host === '' || preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
                throw ValidationException::withMessages(['allowed_domains' => "دامنه «{$line}» معتبر نیست."]);
            }

            $domains[] = ($wildcard ? '*.' : '').$host;
        }

        return $domains === [] ? null : array_values(array_unique($domains));
    }

    /**
     * @return list<string>|null
     */
    private function ips(): ?array
    {
        $ips = [];

        foreach ($this->lines($this->allowed_ips) as $line) {
            [$address, $mask] = array_pad(explode('/', $line, 2), 2, null);

            if (filter_var($address, FILTER_VALIDATE_IP) === false || ($mask !== null && ! ctype_digit($mask)) || ! IpUtils::checkIp($address, $line)) {
                throw ValidationException::withMessages(['allowed_ips' => "IP یا محدوده «{$line}» معتبر نیست."]);
            }

            $ips[] = $line;
        }

        return $ips === [] ? null : array_values(array_unique($ips));
    }

    /**
     * "key=value" per line. Values true/false/numbers are typed.
     *
     * @return array<string, mixed>|null
     */
    private function parseFeatures(): ?array
    {
        $features = [];

        foreach ($this->lines($this->features) as $line) {
            [$key, $value] = array_map('trim', array_pad(explode('=', $line, 2), 2, 'true'));

            if (preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $key) !== 1) {
                throw ValidationException::withMessages(['features' => "نام قابلیت «{$key}» معتبر نیست (حروف انگلیسی، عدد، _ . -)."]);
            }

            $features[$key] = match (true) {
                strtolower($value) === 'true' => true,
                strtolower($value) === 'false' => false,
                is_numeric($value) => $value + 0,
                default => $value,
            };
        }

        return $features === [] ? null : $features;
    }

    /**
     * @param  array<string, mixed>  $features
     */
    private static function featuresToText(array $features): string
    {
        return collect($features)
            ->map(fn ($value, $key) => $key.'='.(is_bool($value) ? ($value ? 'true' : 'false') : $value))
            ->implode("\n");
    }

    private function parseDate(string $field): ?Carbon
    {
        if (trim($this->{$field}) === '') {
            return null;
        }

        return Jalali::parse($this->{$field})
            ?? throw ValidationException::withMessages([$field => 'تاریخ را به شکل ۱۴۰۵/۰۱/۳۱ وارد کنید.']);
    }

    /**
     * @return list<string>
     */
    private function lines(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value) ?: [])));
    }

    public function render()
    {
        return view('livewire.licenses.form', [
            'customers' => Customer::query()->orderBy('name')->get()->mapWithKeys(fn (Customer $customer) => [$customer->id => $customer->display_name]),
            'products' => Product::query()->where('is_active', true)->orWhere('id', $this->product_id ?: 0)->orderBy('name')->pluck('name', 'id'),
            'availableServices' => Service::query()->orderBy('id')->get(),
            'statuses' => LicenseStatus::options(),
        ])->title($this->license ? 'ویرایش لایسنس' : 'لایسنس جدید');
    }
}
