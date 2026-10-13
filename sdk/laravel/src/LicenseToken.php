<?php

namespace Rdsco\LicenseClient;

/**
 * Verifies "rdl1.<payload>.<signature>" tokens issued by the license server.
 */
final class LicenseToken
{
    /**
     * @param  array<string, mixed>  $claims
     */
    private function __construct(
        public readonly string $raw,
        public readonly array $claims,
    ) {}

    /**
     * Returns the token if its Ed25519 signature matches the pinned public key.
     */
    public static function verify(string $token, string $base64PublicKey): ?self
    {
        $parts = explode('.', $token);
        $publicKey = base64_decode($base64PublicKey, true);

        if (count($parts) !== 3 || $parts[0] !== 'rdl1' || $publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
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

        return is_array($claims) ? new self($token, $claims) : null;
    }

    public function isValid(): bool
    {
        return ($this->claims['valid'] ?? false) === true;
    }

    public function reason(): ?string
    {
        return is_string($this->claims['reason'] ?? null) ? $this->claims['reason'] : null;
    }

    public function expiresAt(): int
    {
        return (int) ($this->claims['exp'] ?? 0);
    }

    public function issuedAt(): int
    {
        return (int) ($this->claims['iat'] ?? 0);
    }

    public function keyId(): ?string
    {
        return $this->claims['sub'] ?? null;
    }

    public function challenge(): ?string
    {
        return $this->claims['chl'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function license(): array
    {
        return is_array($this->claims['lic'] ?? null) ? $this->claims['lic'] : [];
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
