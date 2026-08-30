<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Services\PricingService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class FractionalQuantityPricingTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_pricing_uses_decimal_quantity_in_line_total(): void
    {
        $stock = $this->stockItem('RM-001', qty: 20, wac: 100.00);
        $stock->update(['price' => 1000.00]);

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        $bom = Bom::create([
            'bom_no' => 'BOM-FRAC-001',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'stage' => Bom::STAGE_RAW,
        ]);

        BomItem::create([
            'bom_id' => $bom->id,
            'stock_item_code' => $this->testOperationalCode('RM-001'),
            'name' => 'مادة بالكيلو',
            'source' => BomItem::SOURCE_SPEC,
            'qty' => 0.2,
            'unit_cost' => 100.00,
            'issued_qty' => 0,
            'returned_qty' => 0,
        ]);

        $case->update(['stage_key' => CaseRecord::STAGE_COST_CALC]);

        $pricing = app(PricingService::class)->createAndCalculateForCase($case->fresh());
        $item = $pricing->items->first();

        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(0.2, (float) $item->qty, 0.0001);
        $this->assertEqualsWithDelta(200.00, (float) $item->line_total, 0.01);
        $this->assertEqualsWithDelta(200.00, (float) $pricing->computed_total, 0.01);
    }

    public function test_fractional_quantity_one_point_five_totals_correctly(): void
    {
        $stock = $this->stockItem('RM-002', qty: 20, wac: 100.00);
        $stock->update(['price' => 1000.00]);

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        $bom = Bom::create([
            'bom_no' => 'BOM-FRAC-002',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'stage' => Bom::STAGE_RAW,
        ]);

        BomItem::create([
            'bom_id' => $bom->id,
            'stock_item_code' => $this->testOperationalCode('RM-002'),
            'name' => 'مادة بالمتر',
            'source' => BomItem::SOURCE_SPEC,
            'qty' => 1.5,
            'unit_cost' => 100.00,
            'issued_qty' => 0,
            'returned_qty' => 0,
        ]);

        $case->update(['stage_key' => CaseRecord::STAGE_COST_CALC]);

        $pricing = app(PricingService::class)->createAndCalculateForCase($case->fresh());

        $this->assertEqualsWithDelta(1500.00, (float) $pricing->computed_total, 0.01);
    }
}
