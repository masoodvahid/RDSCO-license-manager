<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\Jalali;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (Google Authenticator) with replay protection: a code that was
 * already accepted cannot be used again within its time window.
 */
class TwoFactorAuthenticator
{
    public function __construct(
        private readonly Google2FA $engine,
    ) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $uri = $this->engine->getQRCodeUrl(config('app.name'), $user->email, $secret);

        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'scale' => 4,
        ]);

        return (new QRCode($options))->render($uri);
    }

    /**
     * Check a code against a secret that is not confirmed yet (setup step).
     */
    public function verifySetupCode(string $secret, string $code): bool
    {
        $code = $this->normalize($code);

        return $code !== null && $this->engine->verifyKey($secret, $code, 1);
    }

    /**
     * Check a login code and remember its time step so it cannot be reused.
     */
    public function verifyLoginCode(User $user, string $code): bool
    {
        $code = $this->normalize($code);

        if ($code === null || ! $user->hasTwoFactorEnabled()) {
            return false;
        }

        $step = $this->engine->verifyKeyNewer((string) $user->two_factor_secret, $code, (int) ($user->two_factor_last_used_step ?? 0), 1);

        if ($step === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_used_step' => $step])->save();

        return true;
    }

    /**
     * Use (and burn) a recovery code.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = strtoupper(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $recoveryCode) {
            if (hash_equals((string) $recoveryCode, $code)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn (): string => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)))
            ->all();
    }

    private function normalize(string $code): ?string
    {
        $digits = preg_replace('/\s+/', '', Jalali::toLatinDigits($code)) ?? '';

        return preg_match('/^\d{6}$/', $digits) === 1 ? $digits : null;
    }
}
