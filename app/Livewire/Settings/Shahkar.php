<?php

namespace App\Livewire\Settings;

use App\Http\Controllers\Api\V1\ShahkarController;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Rules\IranianNationalCode;
use App\Services\Shahkar\ShahkarCircuit;
use App\Services\Shahkar\ShahkarClient;
use App\Services\Shahkar\ShahkarException;
use App\Services\Shahkar\ShahkarSettings;
use App\Services\Shahkar\SitaadTokenProvider;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Title('اتصال شاهکار')]
class Shahkar extends Component
{
    /** @var array<string, string> */
    public array $values = [];

    public string $testNationalCode = '';

    public string $testMobile = '';

    /** @var array<string, mixed>|null */
    public ?array $testResult = null;

    public function mount(): void
    {
        foreach (ShahkarSettings::FIELDS as $key => [$label, $isSecret]) {
            // Secrets are never sent to the browser; an empty field keeps the stored value.
            $this->values[$this->field($key)] = $isSecret ? '' : (string) Setting::get($key, match ($key) {
                'shahkar.token_url' => ShahkarSettings::DEFAULT_TOKEN_URL,
                'shahkar.verify_url' => ShahkarSettings::DEFAULT_VERIFY_URL,
                default => '',
            });
        }
    }

    public function save(SitaadTokenProvider $tokens): void
    {
        $this->validate([
            'values.shahkar_token_url' => ['required', 'url:https'],
            'values.shahkar_verify_url' => ['required', 'url:https'],
            'values.shahkar_rid' => ['nullable', 'regex:/^\d{1,10}$/'],
            'values.shahkar_public_key' => ['nullable', 'string', 'max:5000'],
        ], [
            'values.shahkar_rid.regex' => 'RID فقط شامل رقم است.',
        ], [
            'values.shahkar_token_url' => 'آدرس توکن',
            'values.shahkar_verify_url' => 'آدرس سرویس',
        ]);

        $publicKey = trim($this->values['shahkar_public_key'] ?? '');

        if ($publicKey !== '' && (! str_contains($publicKey, 'BEGIN PUBLIC KEY') || openssl_pkey_get_public($publicKey) === false)) {
            $this->addError('values.shahkar_public_key', 'کلید عمومی باید فایل PEM معتبر (BEGIN PUBLIC KEY) باشد.');

            return;
        }

        foreach (ShahkarSettings::FIELDS as $key => [$label, $isSecret]) {
            $value = trim($this->values[$this->field($key)] ?? '');

            if ($isSecret && $value === '') {
                continue;
            }

            Setting::set($key, $value, encrypt: $isSecret);
        }

        $tokens->forgetAll();
        app(ShahkarCircuit::class)->reset();

        foreach (ShahkarSettings::FIELDS as $key => [$label, $isSecret]) {
            if ($isSecret) {
                $this->values[$this->field($key)] = '';
            }
        }

        ActivityLog::record('settings.shahkar', 'ذخیره تنظیمات اتصال شاهکار');
        $this->dispatch('toast', type: 'success', message: 'تنظیمات شاهکار ذخیره شد.');
    }

    public function testToken(SitaadTokenProvider $tokens): void
    {
        $settings = ShahkarSettings::load();
        $startedAt = hrtime(true);

        try {
            $tokens->forgetAll();
            $tokens->token($settings);

            $this->testResult = ['ok' => true, 'title' => 'اتصال به سیتاد و دریافت توکن موفق بود.', 'details' => []];
        } catch (ShahkarException $exception) {
            $this->testResult = ['ok' => false, 'title' => $exception->getMessage(), 'details' => array_filter(['HTTP' => $exception->gatewayStatus])];
        } catch (Throwable $exception) {
            report($exception);
            $this->testResult = ['ok' => false, 'title' => 'خطای داخلی هنگام دریافت توکن.', 'details' => []];
        }

        $this->testResult['details']['مدت'] = round((hrtime(true) - $startedAt) / 1_000_000).'ms';
    }

    public function testVerification(ShahkarClient $client): void
    {
        // A manual test should reach the gateway even if the circuit is open.
        app(ShahkarCircuit::class)->reset();

        $this->testMobile = ShahkarController::normalizeMobile($this->testMobile);

        $this->validate([
            'testNationalCode' => ['required', new IranianNationalCode],
            'testMobile' => ['required', 'regex:/^09\d{9}$/'],
        ], ['testMobile.regex' => 'شماره همراه باید با قالب 09xxxxxxxxx باشد.']);

        $result = $client->verify($this->testNationalCode, $this->testMobile);

        $this->testResult = [
            'ok' => $result->isAnswer(),
            'title' => match ($result->matched) {
                true => 'تطابق برقرار است.',
                false => 'پاسخ دریافت شد: عدم تطابق.',
                null => 'استعلام ناموفق بود.',
            },
            'details' => array_filter([
                'مرحله' => $result->stage,
                'کد پاسخ' => $result->code ?: null,
                'پیام' => $result->message,
                'شماره درخواست' => $result->requestId,
                'شماره پیگیری' => $result->trackingId,
                'مدت' => $result->durationMs.'ms',
            ], fn ($value) => $value !== null && $value !== ''),
        ];

        ActivityLog::record('settings.shahkar_test', 'استعلام آزمایشی شاهکار');
    }

    private function field(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    public function render()
    {
        $settings = ShahkarSettings::load();
        $configured = [];

        foreach (ShahkarSettings::FIELDS as $key => [$label, $isSecret]) {
            $configured[$this->field($key)] = Setting::get($key) !== null;
        }

        return view('livewire.settings.shahkar', [
            'fields' => ShahkarSettings::FIELDS,
            'configured' => $configured,
            'missing' => $settings->missing(),
        ]);
    }
}
