<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an Iranian national code (کد ملی) including its check digit.
 * Rejecting bad codes locally avoids paying for a doomed Shahkar request.
 */
class IranianNationalCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail('کد ملی معتبر نیست.');
        }
    }

    public static function isValid(string $code): bool
    {
        if (preg_match('/^\d{10}$/', $code) !== 1 || preg_match('/^(\d)\1{9}$/', $code) === 1) {
            return false;
        }

        $sum = 0;

        for ($position = 0; $position < 9; $position++) {
            $sum += (int) $code[$position] * (10 - $position);
        }

        $remainder = $sum % 11;
        $checkDigit = (int) $code[9];

        return $remainder < 2 ? $checkDigit === $remainder : $checkDigit === 11 - $remainder;
    }
}
