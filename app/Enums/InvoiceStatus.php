<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Issued = 'issued';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'صادرشده',
            self::Paid => 'پرداخت‌شده',
            self::Void => 'باطل‌شده',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Issued => 'info',
            self::Paid => 'success',
            self::Void => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
