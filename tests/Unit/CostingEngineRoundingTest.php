<?php

namespace Tests\Unit;

use App\Support\CostingEngine;
use PHPUnit\Framework\TestCase;

class CostingEngineRoundingTest extends TestCase
{
    private function mode(): array
    {
        return [
            'key' => 'limb',
            'label' => 'طرف صناعي',
            'profit_rate' => 95,
            'has_components' => true,
            'components' => [
                ['label' => 'فحص', 'rate' => 30],
                ['label' => 'دمج', 'rate' => 25],
                ['label' => 'إهلاك', 'rate' => 23],
                ['label' => 'تأهيل', 'rate' => 22],
            ],
        ];
    }

    public function test_components_sum_to_rate_total_without_rounding_drift(): void
    {
        // كل بند مُقرَّب منفرداً كان يعطي 0.82+0.68+0.63+0.60 = 2.73 لمواد 2.72 بنسب مجموعها 100%.
        $result = (new CostingEngine)->calculate($this->mode(), 2.72);

        $this->assertSame(2.72, $result['components_total']);
        $this->assertEqualsWithDelta(2.72, array_sum(array_column($result['components'], 'amount')), 0.0001);
        $this->assertSame(5.44, $result['total_cost']);
        $this->assertSame(10.61, $result['selling_price']);
    }

    public function test_split_pricing_uses_same_allocation(): void
    {
        $result = (new CostingEngine)->calculateSplit($this->mode(), ['profit_rate' => 40], 2.72, 0);

        $this->assertSame(2.72, $result['components_total']);
        $this->assertSame(10.61, $result['selling_price']);
    }

    public function test_many_amounts_keep_lines_equal_to_total(): void
    {
        $engine = new CostingEngine;
        foreach ([0.01, 0.14, 0.29, 1.07, 99.99, 1234.57, 87654.31] as $materials) {
            $result = $engine->calculate($this->mode(), $materials);
            $lines = round(array_sum(array_column($result['components'], 'amount')), 2);

            $this->assertSame($result['components_total'], $lines, "materials={$materials}");
            $this->assertSame(round($materials, 2), $result['components_total'], "materials={$materials}");
        }
    }
}
