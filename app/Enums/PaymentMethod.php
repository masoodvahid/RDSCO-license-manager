<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'واریز بانکی',
            self::Card => 'کارت به کارت',
            self::Cash => 'نقدی',
            self::Other => 'سایر',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BankTransfer => 'gray',
            self::Card => 'gray',
            self::Cash => 'gray',
            self::Other => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
