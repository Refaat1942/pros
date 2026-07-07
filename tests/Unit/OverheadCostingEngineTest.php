<?php

namespace Tests\Unit;

use App\Models\ContractCompany;
use App\Support\OverheadCostingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverheadCostingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_overheads_and_net_offer_with_company_discount(): void
    {
        $company = ContractCompany::create([
            'company_code' => 'CO-OH',
            'name' => 'جهة خصم',
            'is_military' => false,
            'is_contracted' => true,
            'discount_percent' => 20,
        ]);

        $result = app(OverheadCostingEngine::class)->calculate(10000, $company);

        $this->assertSame(10000.0, $result['materials_total']);
        $this->assertSame(3000.0, $result['overheads'][0]['amount']);
        $this->assertSame(2500.0, $result['overheads'][1]['amount']);
        $this->assertSame(2300.0, $result['overheads'][2]['amount']);
        $this->assertSame(2200.0, $result['overheads'][3]['amount']);
        $this->assertSame(10000.0, $result['overhead_total']);
        $this->assertSame(10000.0, $result['gross_before_discount']);
        $this->assertSame(20.0, $result['discount_percent']);
        $this->assertSame(2000.0, $result['discount_amount']);
        $this->assertSame(8000.0, $result['net_offer_total']);
    }

    public function test_gross_before_discount_equals_materials_total(): void
    {
        $gross = app(OverheadCostingEngine::class)->grossBeforeDiscount(400);

        $this->assertSame(400.0, $gross);
    }
}
