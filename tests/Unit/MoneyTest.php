<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_arithmetic_is_exact_decimal_never_float(): void
    {
        // 0.1 + 0.2 style traps
        $this->assertSame('0.3000', Money::add('0.1', '0.2'));
        $this->assertSame('1000000000000.0001', Money::add('999999999999.9999', '0.0002'));
        $this->assertSame('-0.0100', Money::sub('0.09', '0.10'));
        $this->assertSame('30.0000', Money::sum(['10', '10.0000', '10']));
    }

    public function test_compare(): void
    {
        $this->assertSame(0, Money::cmp('1.5', '1.5000'));
        $this->assertSame(1, Money::cmp('10', '9.9999'));
        $this->assertSame(-1, Money::cmp('-0.0001', '0'));
    }

    public function test_format_groups_thousands_and_rounds_half_up(): void
    {
        $this->assertSame("\u{20A6}1,500.00", Money::format('1500.0000'));
        $this->assertSame("\u{20A6}1,234,567.89", Money::format('1234567.8850'));
        $this->assertSame("\u{20A6}0.01", Money::format('0.005'));
        $this->assertSame("-\u{20A6}500.00", Money::format('-500'));
        $this->assertSame("\u{20A6}0.00", Money::format('-0.0001'));
    }

    public function test_garbage_is_treated_as_zero_not_evaluated(): void
    {
        $this->assertSame('0.0000', Money::add('1e5', 'abc'));
        $this->assertSame("\u{20A6}0.00", Money::format(null));
        $this->assertSame("\u{20A6}0.00", Money::format(['x']));
    }
}
