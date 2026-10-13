<?php

namespace App\Services\Licensing;

use App\Models\Setting;
use RuntimeException;

/**
 * The server's Ed25519 key pair for signing license tokens.
 *
 * The private key is stored encrypted in settings. Client applications pin
 * the public key, so rotating it requires updating every client.
 */
class SigningKey
{
    private const SECRET_KEY = 'signing.secret_key';

    private const PUBLIC_KEY = 'signing.public_key';

    private const KEY_ID = 'signing.key_id';

    public function ensureExists(): void
    {
        if (Setting::get(self::SECRET_KEY) === null) {
            $this->generate();
        }
    }

    /**
     * Create a new key pair. Existing clients stop trusting new tokens until
     * they are given the new public key.
     */
    public function generate(): void
    {
        $keyPair = sodium_crypto_sign_keypair();

        Setting::set(self::SECRET_KEY, base64_encode(sodium_crypto_sign_secretkey($keyPair)), encrypt: true);
        Setting::set(self::PUBLIC_KEY, base64_encode(sodium_crypto_sign_publickey($keyPair)));
        Setting::set(self::KEY_ID, 'k'.now()->format('Ymd').'-'.bin2hex(random_bytes(3)));
    }

    public function sign(string $message): string
    {
        $this->ensureExists();

        $secretKey = base64_decode((string) Setting::get(self::SECRET_KEY), true);

        if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('The license signing key is invalid.');
        }

        return sodium_crypto_sign_detached($message, $secretKey);
    }

    public function publicKey(): string
    {
        $this->ensureExists();

        return (string) Setting::get(self::PUBLIC_KEY);
    }

    public function keyId(): string
    {
        $this->ensureExists();

        return (string) Setting::get(self::KEY_ID);
    }
}
