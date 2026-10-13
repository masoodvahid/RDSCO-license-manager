<?php

namespace App\Support;

use DateTimeInterface;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Jalali (Persian calendar) formatting and parsing for the panel.
 */
final class Jalali
{
    public static function date(?DateTimeInterface $date, string $format = 'Y/m/d'): string
    {
        if ($date === null) {
            return '—';
        }

        return Verta::instance(Carbon::instance($date)->timezone(config('app.timezone')))->format($format);
    }

    public static function dateTime(?DateTimeInterface $date): string
    {
        return self::date($date, 'Y/m/d H:i');
    }

    public static function relative(?DateTimeInterface $date): string
    {
        return $date === null ? '—' : Verta::instance($date)->formatDifference();
    }

    /**
     * Parse "1405/07/21" (Persian or Latin digits) into a Carbon date.
     */
    public static function parse(?string $value): ?Carbon
    {
        $value = trim(self::toLatinDigits((string) $value));

        if ($value === '') {
            return null;
        }

        if (preg_match('#^(\d{4})[/-](\d{1,2})[/-](\d{1,2})$#', $value, $matches) !== 1) {
            return null;
        }

        [, $year, $month, $day] = array_map('intval', $matches);

        if (! Verta::isValidDate($year, $month, $day)) {
            return null;
        }

        try {
            return Carbon::instance(Verta::create($year, $month, $day, 0, 0, 0, config('app.timezone'))->datetime())->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    public static function toLatinDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    public static function currentYear(): int
    {
        return (int) Verta::now()->format('Y');
    }
}
