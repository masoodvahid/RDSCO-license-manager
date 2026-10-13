<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\Auth\TwoFactorAuthenticator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest', ['subtitle' => 'تأیید ورود دومرحله‌ای'])]
#[Title('تأیید دومرحله‌ای')]
class TwoFactorChallenge extends Component
{
    public string $code = '';

    public bool $useRecoveryCode = false;

    public function mount(): void
    {
        if ($this->pendingUser() === null) {
            $this->redirectRoute('login', navigate: false);
        }
    }

    public function verify(TwoFactorAuthenticator $twoFactor): void
    {
        $this->validate(['code' => 'required|string|max:32']);

        $user = $this->pendingUser();

        if ($user === null) {
            $this->redirectRoute('login', navigate: false);

            return;
        }

        $throttleKey = 'two-factor:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'code' => 'تلاش‌های ناموفق زیاد بود. '.RateLimiter::availableIn($throttleKey).' ثانیه دیگر دوباره تلاش کنید.',
            ]);
        }

        $isValid = $this->useRecoveryCode
            ? $twoFactor->useRecoveryCode($user, $this->code)
            : $twoFactor->verifyLoginCode($user, $this->code);

        if (! $isValid) {
            RateLimiter::hit($throttleKey, 60);
            $this->reset('code');

            throw ValidationException::withMessages(['code' => 'کد وارد شده معتبر نیست.']);
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) (session(Login::PENDING_SESSION_KEY)['remember'] ?? false);
        session()->forget(Login::PENDING_SESSION_KEY);

        Login::completeLogin($user, $remember);

        $this->redirectIntended(route('dashboard'), navigate: false);
    }

    public function toggleRecovery(): void
    {
        $this->useRecoveryCode = ! $this->useRecoveryCode;
        $this->reset('code');
        $this->resetValidation();
    }

    private function pendingUser(): ?User
    {
        $pending = session(Login::PENDING_SESSION_KEY);

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            session()->forget(Login::PENDING_SESSION_KEY);

            return null;
        }

        return User::query()->find($pending['user_id'] ?? null);
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge');
    }
}
