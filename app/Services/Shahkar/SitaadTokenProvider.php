<?php

namespace App\Services\Shahkar;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OAuth access token for the Sitaad gateway.
 *
 * Tokens live only a few minutes (expires_in ~ 359s), so they are cached
 * (encrypted) and renewed with the refresh token when possible, falling back
 * to the password grant. A lock prevents concurrent requests from all
 * fetching a new token at once.
 */
class SitaadTokenProvider
{
    private const ACCESS_TOKEN_KEY = 'shahkar:access-token';

    private const REFRESH_TOKEN_KEY = 'shahkar:refresh-token';

    private const EXPIRY_MARGIN_SECONDS = 30;

    public function token(ShahkarSettings $settings): string
    {
        $cached = $this->cached(self::ACCESS_TOKEN_KEY, $settings);

        if ($cached !== null) {
            return $cached;
        }

        return Cache::lock('shahkar:token-lock', 20)->block(10, function () use ($settings): string {
            // Another request may have refreshed it while we waited.
            return $this->cached(self::ACCESS_TOKEN_KEY, $settings) ?? $this->fetch($settings);
        });
    }

    public function forget(): void
    {
        Cache::forget(self::ACCESS_TOKEN_KEY);
    }

    public function forgetAll(): void
    {
        Cache::forget(self::ACCESS_TOKEN_KEY);
        Cache::forget(self::REFRESH_TOKEN_KEY);
    }

    private function fetch(ShahkarSettings $settings): string
    {
        $refreshToken = $this->cached(self::REFRESH_TOKEN_KEY, $settings);

        if ($refreshToken !== null) {
            $response = $this->request($settings, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

            if ($response?->successful() && is_string($response->json('access_token'))) {
                return $this->store($response, $settings);
            }

            Cache::forget(self::REFRESH_TOKEN_KEY);
        }

        $response = $this->request($settings, [
            'grant_type' => 'password',
            'username' => $settings->username,
            'password' => $settings->password,
        ]);

        if ($response === null) {
            throw new ShahkarException('token', 'connection', 'ارتباط با سیتاد برای دریافت توکن برقرار نشد.', retryable: true);
        }

        $accessToken = $response->json('access_token');

        if (! $response->successful() || ! is_string($accessToken) || $accessToken === '') {
            throw new ShahkarException('token', 'authentication', 'سیتاد درخواست دریافت توکن را رد کرد. اطلاعات اتصال را بررسی کنید.', gatewayStatus: $response->status());
        }

        return $this->store($response, $settings);
    }

    /**
     * @param  array<string, string>  $form
     */
    private function request(ShahkarSettings $settings, array $form): ?Response
    {
        try {
            return Http::asForm()
                ->acceptJson()
                ->withHeaders(['Authorization' => $settings->gatewayAuthorizationHeader()])
                ->connectTimeout(3)
                ->timeout(8)
                ->withoutRedirecting()
                ->post($settings->tokenUrl, $form);
        } catch (ConnectionException) {
            return null;
        }
    }

    private function store(Response $response, ShahkarSettings $settings): string
    {
        $accessToken = (string) $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 300);
        $ttl = max(30, $expiresIn - self::EXPIRY_MARGIN_SECONDS);

        Cache::put(self::ACCESS_TOKEN_KEY, Crypt::encryptString($settings->fingerprint().'|'.$accessToken), $ttl);

        $refreshToken = $response->json('refresh_token');

        if (is_string($refreshToken) && $refreshToken !== '') {
            Cache::put(self::REFRESH_TOKEN_KEY, Crypt::encryptString($settings->fingerprint().'|'.$refreshToken), now()->addHours(12));
        }

        return $accessToken;
    }

    /**
     * Cached values are bound to the credentials they were issued for, so
     * changing settings invalidates them automatically.
     */
    private function cached(string $key, ShahkarSettings $settings): ?string
    {
        $value = Cache::get($key);

        if (! is_string($value)) {
            return null;
        }

        try {
            [$fingerprint, $token] = explode('|', Crypt::decryptString($value), 2) + [1 => ''];
        } catch (Throwable) {
            Cache::forget($key);

            return null;
        }

        return hash_equals($settings->fingerprint(), $fingerprint) && $token !== '' ? $token : null;
    }
}
