<?php

namespace Tests\Unit;

use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JalaliTest extends TestCase
{
    public function test_jalali_dates_round_trip(): void
    {
        $date = Jalali::parse('1405/01/01');

        $this->assertSame('2026-03-21', $date->toDateString());
        $this->assertSame('1405/01/01', Jalali::date($date));
        $this->assertSame('2027-04-04', Jalali::parse('۱۴۰۶/۰۱/۱۵')->toDateString());
        $this->assertSame('1405/07/21', Jalali::date(Carbon::parse('2026-10-13')));
    }

    public function test_invalid_dates_are_rejected(): void
    {
        foreach (['', '1405/13/01', '1405/02/32', '2026-10-13x', 'tomorrow'] as $value) {
            $this->assertNull(Jalali::parse($value), $value);
        }
    }

    public function test_money_parsing_accepts_separators_and_persian_digits(): void
    {
        $this->assertSame(1_500_000, Money::parse('1,500,000'));
        $this->assertSame(25_000, Money::parse('۲۵٬۰۰۰ ریال'));
        $this->assertSame('1,500,000 ریال', Money::format(1_500_000));
    }
}
