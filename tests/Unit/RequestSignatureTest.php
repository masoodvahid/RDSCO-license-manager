<?php

namespace Tests\Unit;

use App\Services\Licensing\RequestSignature;
use PHPUnit\Framework\TestCase;

class RequestSignatureTest extends TestCase
{
    /**
     * Fixed vector shared with the client SDK documentation.
     */
    public function test_canonical_string_and_signature_match_the_documented_vector(): void
    {
        $canonical = RequestSignature::canonical('post', 'api/v1/license/verify', '1760000000', 'n-0123456789abcdef', '{"domain":"crm.example.com"}');

        $this->assertSame(
            "POST\n/api/v1/license/verify\n1760000000\nn-0123456789abcdef\n".hash('sha256', '{"domain":"crm.example.com"}'),
            $canonical,
        );

        $signature = RequestSignature::sign('sk_test', 'POST', '/api/v1/license/verify', '1760000000', 'n-0123456789abcdef', '{"domain":"crm.example.com"}');

        $this->assertSame(hash_hmac('sha256', $canonical, 'sk_test'), $signature);
        $this->assertTrue(RequestSignature::verify(strtoupper($signature), 'sk_test', 'POST', '/api/v1/license/verify', '1760000000', 'n-0123456789abcdef', '{"domain":"crm.example.com"}'));
        $this->assertFalse(RequestSignature::verify($signature, 'sk_other', 'POST', '/api/v1/license/verify', '1760000000', 'n-0123456789abcdef', '{"domain":"crm.example.com"}'));
    }
}
