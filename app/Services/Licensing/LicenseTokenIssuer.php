<?php

namespace App\Services\Licensing;

/**
 * Issues compact Ed25519-signed license tokens:
 *
 *   rdl1.<base64url(JSON payload)>.<base64url(signature)>
 *
 * The signature covers "rdl1.<base64url(JSON payload)>". Clients verify it
 * with the pinned public key and trust the payload only if it matches.
 */
class LicenseTokenIssuer
{
    public const PREFIX = 'rdl1';

    public function __construct(
        private readonly SigningKey $signingKey,
    ) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public function issue(array $claims): string
    {
        $payload = self::base64UrlEncode((string) json_encode(
            ['kid' => $this->signingKey->keyId(), ...$claims],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        $signedPart = self::PREFIX.'.'.$payload;

        return $signedPart.'.'.self::base64UrlEncode($this->signingKey->sign($signedPart));
    }

    /**
     * Verify a token against a base64 public key and return its claims.
     *
     * @return array<string, mixed>|null
     */
    public static function verify(string $token, string $base64PublicKey): ?array
    {
        $parts = explode('.', $token);
        $publicKey = base64_decode($base64PublicKey, true);

        if (count($parts) !== 3 || $parts[0] !== self::PREFIX || $publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        $signature = self::base64UrlDecode($parts[2]);

        if ($signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        if (! sodium_crypto_sign_verify_detached($signature, $parts[0].'.'.$parts[1], $publicKey)) {
            return null;
        }

        $claims = json_decode((string) self::base64UrlDecode($parts[1]), true);

        return is_array($claims) ? $claims : null;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
