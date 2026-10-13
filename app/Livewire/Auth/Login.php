<?php

namespace App\Livewire\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('ورود')]
class Login extends Component
{
    public const PENDING_SESSION_KEY = 'login.two_factor_pending';

    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $throttleKey = 'login:'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'تلاش‌های ناموفق زیاد بود. '.RateLimiter::availableIn($throttleKey).' ثانیه دیگر دوباره تلاش کنید.',
            ]);
        }

        $user = User::query()->where('email', Str::lower(trim($this->email)))->first();

        if ($user === null || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages(['email' => 'ایمیل یا رمز عبور اشتباه است.']);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        // With 2FA the user is not logged in until the code is verified.
        if ($user->hasTwoFactorEnabled()) {
            session()->put(self::PENDING_SESSION_KEY, [
                'user_id' => $user->getKey(),
                'remember' => $this->remember,
                'expires_at' => now()->addMinutes(5)->getTimestamp(),
            ]);

            $this->redirectRoute('two-factor.challenge', navigate: false);

            return;
        }

        self::completeLogin($user, $this->remember);

        $this->redirectIntended(route('dashboard'), navigate: false);
    }

    public static function completeLogin(User $user, bool $remember): void
    {
        Auth::login($user, $remember);
        session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        ActivityLog::record('auth.login', 'ورود به پنل');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
