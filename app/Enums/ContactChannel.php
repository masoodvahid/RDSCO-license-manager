<?php

namespace App\Enums;

enum ContactChannel: string
{
    case Bale = 'bale';
    case Eitaa = 'eitaa';
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Bale => 'بله',
            self::Eitaa => 'ایتا',
            self::Telegram => 'تلگرام',
            self::WhatsApp => 'واتساپ',
            self::Email => 'ایمیل',
        };
    }

    public function color(): string
    {
        return 'gray';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
