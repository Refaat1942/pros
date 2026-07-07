<?php

namespace Tests\Unit;

use App\Support\CostingEngine;
use PHPUnit\Framework\TestCase;

class CostingEngineTest extends TestCase
{
    private function limbMode(): array
    {
        return [
            'key' => 'prosthetic_limb',
            'label' => 'طرف صناعي',
            'profit_rate' => 95.0,
            'has_components' => true,
            'components' => [
                ['label' => 'أ', 'rate' => 30.0],
                ['label' => 'ب', 'rate' => 25.0],
                ['label' => 'ج', 'rate' => 23.0],
                ['label' => 'د', 'rate' => 22.0],
            ],
        ];
    }

    private function quickMode(): array
    {
        return [
            'key' => 'quick_dispense',
            'label' => 'الصرف السريع',
            'profit_rate' => 40.0,
            'has_components' => false,
            'components' => [],
        ];
    }

    public function test_limb_mode_adds_components_then_profit(): void
    {
        $result = (new CostingEngine)->calculate($this->limbMode(), 1000.0);

        // components 30+25+23+22 = 100% of materials => 1000
        $this->assertSame(1000.0, $result['components_total']);
        // total_cost = materials + components = 2000
        $this->assertSame(2000.0, $result['total_cost']);
        // selling = 2000 * 1.95 = 3900
        $this->assertSame(3900.0, $result['selling_price']);
        $this->assertSame(1900.0, $result['profit_amount']);
        $this->assertCount(4, $result['components']);
    }

    public function test_quick_mode_applies_profit_directly_on_materials(): void
    {
        $result = (new CostingEngine)->calculate($this->quickMode(), 1000.0);

        $this->assertSame(0.0, $result['components_total']);
        $this->assertSame(1000.0, $result['total_cost']);
        $this->assertSame(1400.0, $result['selling_price']);
        $this->assertSame([], $result['components']);
    }

    public function test_no_mode_returns_materials_without_profit(): void
    {
        $result = (new CostingEngine)->calculate(null, 750.0);

        $this->assertSame(750.0, $result['total_cost']);
        $this->assertSame(750.0, $result['selling_price']);
        $this->assertSame(0.0, $result['profit_rate']);
        $this->assertNull($result['mode_key']);
    }

    public function test_negative_materials_are_clamped_to_zero(): void
    {
        $result = (new CostingEngine)->calculate($this->quickMode(), -500.0);

        $this->assertSame(0.0, $result['materials_total']);
        $this->assertSame(0.0, $result['selling_price']);
    }
}
