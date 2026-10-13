<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\ShahkarController;
use App\Rules\IranianNationalCode;
use PHPUnit\Framework\TestCase;

class IranianNationalCodeTest extends TestCase
{
    public function test_check_digits_are_validated(): void
    {
        foreach (['0499370899', '0790419904', '0084575948'] as $valid) {
            $this->assertTrue(IranianNationalCode::isValid($valid), $valid);
        }

        foreach (['0499370898', '1111111111', '123456789', '12345678901', 'abcdefghij', ''] as $invalid) {
            $this->assertFalse(IranianNationalCode::isValid($invalid), $invalid);
        }
    }

    public function test_mobile_numbers_are_normalized(): void
    {
        foreach (['09123456789', '9123456789', '989123456789', '+98 912 345 6789', '00989123456789', '۰۹۱۲۳۴۵۶۷۸۹'] as $input) {
            $this->assertSame('09123456789', ShahkarController::normalizeMobile($input), $input);
        }
    }
}
