<?php

namespace Tests\Unit;

use App\Support\StockQuantity;
use InvalidArgumentException;
use Tests\TestCase;

class StockQuantityTest extends TestCase
{
    public function test_parse_grams_to_kilo_value(): void
    {
        $parsed = StockQuantity::parse('100 جرام', 'كيلو');

        $this->assertSame('جرام', $parsed['uom']);
        $this->assertSame(100.0, $parsed['value']);
    }

    public function test_to_uom_grams_to_kilo(): void
    {
        $kg = StockQuantity::toUom(100, 'جرام', 'كيلو');

        $this->assertEquals(0.1, $kg);
    }

    public function test_line_cost_fractional(): void
    {
        $cost = StockQuantity::lineCost(0.1, 1000.0);

        $this->assertEquals(100.0, $cost);
    }

    public function test_is_fractional_uom_for_mass_and_count(): void
    {
        $this->assertTrue(StockQuantity::isFractionalUom('كيلو'));
        $this->assertTrue(StockQuantity::isFractionalUom('متر'));
        $this->assertFalse(StockQuantity::isFractionalUom('قطعة'));
    }

    public function test_parse_invalid_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StockQuantity::parse('abc');
    }
}
