<?php

namespace App\Enums;

enum UsageStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'موفق',
            self::Failed => 'ناموفق',
            self::Error => 'خطا',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Failed => 'warning',
            self::Error => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
