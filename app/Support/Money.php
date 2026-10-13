<?php

namespace App\Support;

/**
 * Amounts are stored as integer Rials.
 */
final class Money
{
    public static function format(int|float|null $rials, bool $withUnit = true): string
    {
        $formatted = number_format((int) ($rials ?? 0));

        return $withUnit ? $formatted.' ریال' : $formatted;
    }

    public static function parse(mixed $value): int
    {
        $digits = preg_replace('/[^\d]/', '', Jalali::toLatinDigits((string) $value));

        return $digits === '' || $digits === null ? 0 : (int) $digits;
    }
}
