<?php

namespace App\Services\Shahkar;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA256KW;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use Jose\Component\KeyManagement\JWKFactory;
use RuntimeException;
use Throwable;

/**
 * JWE encryption required by "serviceIDmatchingencrypted":
 * ECDH-ES+A256KW key wrapping, A256GCM content encryption, compact form,
 * payload {"data": "<value>", "iat": <unix time>}.
 */
class ShahkarEncryptor
{
    /**
     * Protected header used in production with the previous implementation.
     * "alpha" is kept as-is because the gateway accepted it.
     */
    private const PROTECTED_HEADER = [
        'alg' => 'ECDH-ES+A256KW',
        'enc' => 'A256GCM',
        'typ' => 'JWT',
        'alpha' => '8mkn2h6yd5488veaq4s77',
    ];

    /**
     * Shahkar's servers may run a few seconds behind; back-date "iat" so a
     * token is never "issued in the future" from their point of view.
     */
    private const ISSUED_AT_SKEW_SECONDS = 60;

    public function encrypt(string $value, string $publicKeyPem, ?int $issuedAt = null): string
    {
        if (! str_contains($publicKeyPem, 'BEGIN PUBLIC KEY')) {
            throw new RuntimeException('Shahkar public key must be a PEM "PUBLIC KEY".');
        }

        try {
            $publicKey = JWKFactory::createFromKey($publicKeyPem);
        } catch (Throwable $exception) {
            throw new RuntimeException('Shahkar public key could not be loaded.', previous: $exception);
        }

        $payload = json_encode([
            'data' => $value,
            'iat' => $issuedAt ?? (time() - self::ISSUED_AT_SKEW_SECONDS),
        ], JSON_THROW_ON_ERROR);

        $jwe = (new JWEBuilder(new AlgorithmManager([new ECDHESA256KW, new A256GCM])))
            ->create()
            ->withPayload($payload)
            ->withSharedProtectedHeader(self::PROTECTED_HEADER)
            ->addRecipient($publicKey)
            ->build();

        return (new CompactSerializer)->serialize($jwe, 0);
    }
}
