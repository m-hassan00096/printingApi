<?php

namespace Modules\PrintingServices\Tests\Unit;

use Modules\PrintingServices\Enums\PricingMethod;
use Modules\PrintingServices\Services\AreaPriceCalculator;
use PHPUnit\Framework\TestCase;

class AreaPriceCalculatorTest extends TestCase
{
    public function test_quantity_pricing(): void
    {
        $result = (new AreaPriceCalculator())->calculate(PricingMethod::QUANTITY, '50.00', 10, null, null);
        self::assertSame('500.00', $result['total']);
        self::assertNull($result['area']);
    }

    public function test_square_centimeter_pricing(): void
    {
        $result = (new AreaPriceCalculator())->calculate(PricingMethod::SQUARE_CENTIMETER, '0.50', 10, '20', '15');
        self::assertSame('300', $result['area']);
        self::assertSame('1500.00', $result['total']);
    }

    public function test_square_meter_pricing(): void
    {
        $result = (new AreaPriceCalculator())->calculate(PricingMethod::SQUARE_METER, '100', 3, '200', '150');
        self::assertSame('3', $result['area']);
        self::assertSame('900.00', $result['total']);
    }

    public function test_price_is_rounded_once_at_line_total(): void
    {
        $result = (new AreaPriceCalculator())->calculate(PricingMethod::SQUARE_CENTIMETER, '0.025', 3, '1', '1');
        self::assertSame('0.08', $result['total']);
    }

    public function test_decimal_dimensions_are_precise(): void
    {
        $result = (new AreaPriceCalculator())->calculate(PricingMethod::SQUARE_CENTIMETER, '0.025', 10, '20.5', '15');
        self::assertSame('76.88', $result['total']);
    }
}
