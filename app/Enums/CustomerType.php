<?php

namespace App\Enums;

enum CustomerType: string
{
    case Individual = 'individual';
    case Legal = 'legal';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'حقیقی',
            self::Legal => 'حقوقی',
        };
    }

    /**
     * Label of the identity number for this kind of customer.
     */
    public function nationalIdLabel(): string
    {
        return match ($this) {
            self::Individual => 'کد ملی',
            self::Legal => 'شناسه ملی',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
