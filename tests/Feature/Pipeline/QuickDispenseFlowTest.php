<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\StockItem;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * منتجات الصرف السريع (عكاز/كرسي…): مريض كاش كل طلبه صرف سريع → بعد الدفع يروح المخزن
 * يصرف ويبقى جاهز للتسليم من غير مكتب التشغيل ولا قسم الإنتاج. أي بند تصنيع = المسار العادي.
 */
class QuickDispenseFlowTest extends TestCase
{
    use ProstheticTestHelper;

    private function payAtCashier(CaseRecord $case): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->userWithRole('cashier'))
            ->postJson('/cashier/payments/'.$case->id.'/confirm', ['method' => 'cash'])
            ->assertOk();
    }

    public function test_quick_only_cash_case_skips_operations_and_production(): void
    {
        $crutch = $this->stockItem('RM-Q01', qty: 10, wac: 200, quick: true);
        $case = $this->operationsReadyCase($this->cashPatient(), ['RM-Q01']);
        $this->assertSame(CaseRecord::STAGE_CASHIER, $case->fresh()->stage_key);

        $res = $this->payAtCashier($case);
        $this->assertStringContainsString('صرف سريع', $res->json('message'));

        $case->refresh();
        $this->assertSame(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertSame(CaseRecord::MFG_WAREHOUSE, $case->manufacturing_stage);
        $this->assertNotNull($case->work_order_no);

        $bom = Bom::with('items')->where('case_id', $case->id)->firstOrFail();
        // من غير أي تخصيص لقسم الإنتاج أو فني — المخزن يصرف على طول.
        $this->assertNull($case->workshop_section_id);
        $this->assertNotNull($case->quick_dispense_at);
        app(\App\Services\BomService::class)->releaseToWip($bom->fresh(), ['BC-'.$bom->items->first()->stock_item_code]);

        $case->refresh();
        $this->assertSame(CaseRecord::STAGE_READY_DELIVERY, $case->stage_key);
        $this->assertSame(Bom::STAGE_FINISHED, $bom->fresh()->stage);
        $this->assertEquals(9.0, (float) $crutch->fresh()->qty);
    }

    public function test_mixed_case_keeps_normal_path(): void
    {
        $this->stockItem('RM-Q01', qty: 10, quick: true);
        $this->stockItem('RM-001', qty: 10);
        $case = $this->operationsReadyCase($this->cashPatient(), ['RM-Q01', 'RM-001']);

        $this->payAtCashier($case);

        $case->refresh();
        $this->assertSame(CaseRecord::STAGE_OPERATIONS, $case->stage_key);
        $this->assertNull($case->work_order_no);
        $this->assertNull($case->quick_dispense_at);
    }

    public function test_quick_only_contracted_case_is_unchanged(): void
    {
        $this->stockItem('RM-Q01', qty: 10, quick: true);
        $case = $this->dispensedManufacturingCase($this->civilianPatient($this->civilianCompany()), ['RM-Q01']);

        // مسار التعاقد زي ما هو: بعد الصرف يدخل قسم الإنتاج (مش جاهز للتسليم تلقائياً).
        $this->assertSame(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertSame(CaseRecord::MFG_ISSUE, $case->manufacturing_stage);
        $this->assertNull($case->quick_dispense_at);
    }

    public function test_costing_split_detects_quick_items_stored_by_operational_code(): void
    {
        $this->stockItem('RM-Q01', qty: 10, wac: 100, quick: true);
        $case = $this->costCalcReadyCase($this->cashPatient(), ['RM-Q01']);

        $res = $this->actingAs($this->userWithRole('admin'))->getJson('/costing/queue/'.$case->id)->assertOk();
        $this->assertGreaterThan(0, (float) $res->json('costing.quick_materials'));
        $this->assertEquals(0.0, (float) $res->json('costing.base_materials'));
        $this->assertTrue($res->json('pricing.items.0.is_quick_dispense'));
        $this->assertTrue(\App\Support\QuickDispense::caseIsQuickOnly($case->fresh()));
    }

    public function test_spec_quick_search_returns_only_quick_items(): void
    {
        $this->stockItem('RM-Q01', qty: 5, quick: true);
        $this->stockItem('RM-001', qty: 5);
        $spec = $this->userWithRole('spec');

        $rows = $this->actingAs($spec)->getJson('/spec/catalog/search?quick=1')->assertOk()->json('data');
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['is_quick_dispense']);
        $this->assertSame(StockItem::where('is_quick_dispense', true)->first()->name, $rows[0]['name']);

        // البحث العادي من غير quick زي ما هو (لازم نص بحث).
        $this->actingAs($spec)->getJson('/spec/catalog/search')->assertStatus(422);
    }
}
