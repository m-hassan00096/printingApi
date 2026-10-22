<?php

namespace Modules\PrintingServices\Tests\Unit;

use Modules\PrintingServices\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_two_decimal_prices_multiply_exactly(): void
    {
        $this->assertSame('1150.00', Money::total('5.75', 200));
        $this->assertSame('0.00', Money::total('0.00', 999));
        $this->assertSame('0.03', Money::total('0.01', 3));
        $this->assertSame('1234.00', Money::total('12.34', 100));
        $this->assertSame('370.35', Money::total('123.45', 3));
    }

    public function test_unit_price_validation_distinguishes_free_base_from_paid_tier(): void
    {
        $this->assertTrue(Money::valid('0.00', true));
        $this->assertFalse(Money::valid('0.00', false));
        $this->assertTrue(Money::valid('0.01', false));
        $this->assertTrue(Money::valid('999.99', false));
        $this->assertFalse(Money::valid('-1.00', false));
        $this->assertFalse(Money::valid('5.123', false));
        $this->assertFalse(Money::valid('10e2', false));
    }
}
