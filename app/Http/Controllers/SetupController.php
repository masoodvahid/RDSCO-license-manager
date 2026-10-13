<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Licensing\SigningKey;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One-time installer for hosts without SSH: runs migrations, creates the
 * signing key and the first administrator.
 *
 * Only available while SETUP_TOKEN is set in .env and no user exists.
 */
class SetupController extends Controller
{
    public function show(Request $request): View
    {
        $this->authorizeSetup($request);

        return view('setup', [
            'token' => (string) $request->query('token'),
            'needsMigration' => ! Schema::hasTable('users'),
        ]);
    }

    public function store(Request $request, SigningKey $signingKey): RedirectResponse
    {
        $this->authorizeSetup($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        @set_time_limit(300);

        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            return back()->withErrors(['email' => 'اجرای Migration ناموفق بود: '.Str::limit(Artisan::output(), 500)]);
        }

        if (User::query()->exists()) {
            throw new NotFoundHttpException;
        }

        $signingKey->ensureExists();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'password' => $data['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('profile')->with('toast', [
            'type' => 'success',
            'message' => 'نصب کامل شد. SETUP_TOKEN را از فایل .env حذف کنید و ورود دومرحله‌ای را فعال کنید.',
        ]);
    }

    private function authorizeSetup(Request $request): void
    {
        $token = (string) config('app.setup_token');

        $alreadyInstalled = Schema::hasTable('users') && User::query()->exists();

        if ($token === '' || $alreadyInstalled || ! hash_equals($token, (string) $request->input('token', $request->query('token')))) {
            throw new NotFoundHttpException;
        }
    }
}
