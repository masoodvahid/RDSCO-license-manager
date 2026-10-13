<?php

namespace Tests\Feature\Panel;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/licenses')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('ورود');
    }

    public function test_an_administrator_can_log_in_without_two_factor(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'admin@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_credentials_are_rejected_and_throttled(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            Livewire::test(Login::class)
                ->set('email', 'admin@example.com')
                ->set('password', 'wrong')
                ->call('login')
                ->assertHasErrors('email');
        }

        Livewire::test(Login::class)
            ->set('email', 'admin@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_two_factor_users_are_only_logged_in_after_a_valid_code(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => ['AAAAA-BBBBB']])->save();

        Livewire::test(Login::class)
            ->set('email', 'admin@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors('code');

        $this->assertGuest();

        $code = $google2fa->getCurrentOtp($secret);

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', $code)
            ->call('verify')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        // The same code cannot be used again.
        auth()->logout();
        session()->put(Login::PENDING_SESSION_KEY, ['user_id' => $user->id, 'remember' => false, 'expires_at' => now()->addMinute()->timestamp]);

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', $code)
            ->call('verify')
            ->assertHasErrors('code');

        // A recovery code works once.
        Livewire::test(TwoFactorChallenge::class)
            ->set('useRecoveryCode', true)
            ->set('code', 'aaaaa-bbbbb')
            ->call('verify')
            ->assertRedirect(route('dashboard'));

        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);
    }

    public function test_the_challenge_page_requires_a_pending_login(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }
}
