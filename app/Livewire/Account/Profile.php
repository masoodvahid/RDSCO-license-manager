<?php

namespace App\Livewire\Account;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthenticator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('حساب کاربری')]
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** Pending secret while 2FA is being set up (not saved until confirmed). */
    #[Locked]
    public ?string $pendingSecret = null;

    public string $confirmationCode = '';

    /** @var list<string>|null Shown once after enabling or regenerating. */
    public ?array $recoveryCodes = null;

    public string $disablePassword = '';

    public function mount(): void
    {
        $this->name = $this->user()->name;
        $this->email = $this->user()->email;
    }

    public function saveProfile(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
        ]);

        $this->user()->update(['name' => $data['name'], 'email' => Str::lower($data['email'])]);
        $this->dispatch('toast', type: 'success', message: 'مشخصات ذخیره شد.');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], attributes: ['current_password' => 'رمز فعلی', 'password' => 'رمز جدید']);

        $this->user()->update(['password' => $this->password]);
        $this->reset(['current_password', 'password', 'password_confirmation']);

        ActivityLog::record('auth.password_changed', 'تغییر رمز عبور');
        $this->dispatch('toast', type: 'success', message: 'رمز عبور تغییر کرد.');
    }

    public function startTwoFactor(TwoFactorAuthenticator $twoFactor): void
    {
        $this->pendingSecret = $twoFactor->generateSecret();
        $this->reset(['confirmationCode', 'recoveryCodes']);
    }

    public function confirmTwoFactor(TwoFactorAuthenticator $twoFactor): void
    {
        $this->resetErrorBag();

        if ($this->pendingSecret === null) {
            return;
        }

        if (! $twoFactor->verifySetupCode($this->pendingSecret, $this->confirmationCode)) {
            throw ValidationException::withMessages(['confirmationCode' => 'کد وارد شده معتبر نیست. ساعت گوشی را بررسی کنید.']);
        }

        $codes = $twoFactor->generateRecoveryCodes();

        $this->user()->forceFill([
            'two_factor_secret' => $this->pendingSecret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => null,
        ])->save();

        $this->pendingSecret = null;
        $this->recoveryCodes = $codes;
        $this->reset('confirmationCode');

        ActivityLog::record('auth.two_factor_enabled', 'فعال‌سازی ورود دومرحله‌ای');
        $this->dispatch('toast', type: 'success', message: 'ورود دومرحله‌ای فعال شد. کدهای بازیابی را ذخیره کنید.');
    }

    public function regenerateRecoveryCodes(TwoFactorAuthenticator $twoFactor): void
    {
        $codes = $twoFactor->generateRecoveryCodes();
        $this->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $this->recoveryCodes = $codes;
    }

    public function disableTwoFactor(): void
    {
        $this->resetErrorBag();

        if (! Hash::check($this->disablePassword, $this->user()->password)) {
            throw ValidationException::withMessages(['disablePassword' => 'رمز عبور اشتباه است.']);
        }

        $this->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();

        $this->reset(['disablePassword', 'recoveryCodes']);

        ActivityLog::record('auth.two_factor_disabled', 'غیرفعال‌سازی ورود دومرحله‌ای');
        $this->dispatch('toast', type: 'warning', message: 'ورود دومرحله‌ای غیرفعال شد.');
    }

    private function user(): User
    {
        return auth()->user();
    }

    public function render(TwoFactorAuthenticator $twoFactor)
    {
        return view('livewire.account.profile', [
            'enabled' => $this->user()->hasTwoFactorEnabled(),
            'qrCode' => $this->pendingSecret ? $twoFactor->qrCodeSvg($this->user(), $this->pendingSecret) : null,
            'remainingCodes' => count($this->user()->two_factor_recovery_codes ?? []),
        ]);
    }
}
