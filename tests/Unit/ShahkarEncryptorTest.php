<?php

namespace Tests\Unit;

use App\Services\Shahkar\ShahkarEncryptor;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\ECKey;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA256KW;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use Jose\Component\KeyManagement\JWKFactory;
use PHPUnit\Framework\TestCase;
use Throwable;

class ShahkarEncryptorTest extends TestCase
{
    public function test_values_are_encrypted_as_a_compact_ecdh_es_a256kw_jwe_with_data_and_iat_claims(): void
    {
        try {
            $privateKey = JWKFactory::createECKey('P-521');
        } catch (Throwable $exception) {
            $this->markTestSkipped('This PHP build cannot generate EC keys: '.$exception->getMessage());
        }

        $publicKeyPem = self::publicKeyPem($privateKey->toPublic()->all());
        $token = (new ShahkarEncryptor)->encrypt('09120000000', $publicKeyPem, issuedAt: 1_675_065_671);

        $jwe = (new CompactSerializer)->unserialize($token);
        $this->assertSame('ECDH-ES+A256KW', $jwe->getSharedProtectedHeaderParameter('alg'));
        $this->assertSame('A256GCM', $jwe->getSharedProtectedHeaderParameter('enc'));

        $decrypter = new JWEDecrypter(new AlgorithmManager([new ECDHESA256KW, new A256GCM]));
        $this->assertTrue($decrypter->decryptUsingKey($jwe, $privateKey, 0));
        $this->assertSame(['data' => '09120000000', 'iat' => 1_675_065_671], json_decode((string) $jwe->getPayload(), true));
    }

    /**
     * @param  array<string, string>  $jwk
     */
    private static function publicKeyPem(array $jwk): string
    {
        $key = openssl_pkey_get_details(openssl_pkey_get_public(ECKey::convertPublicKeyToPEM(new JWK($jwk))));

        return $key['key'];
    }
}
