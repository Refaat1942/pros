<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Models\PricingRequestItem;
use App\Models\Quote;
use App\Models\StockItem;
use App\Services\BomService;
use App\Services\DeliveryService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Feature — Complete Military Pipeline (end-to-end).
 *
 * الفصل الرابع — المسار العسكري:
 *   1. No quote is generated.
 *   2. No financial freeze.
 *   3. Cost is evaluated *silently* in the background.
 *   4. Case jumps directly to manufacturing.
 *   5. Delivery closes the case and posts to sovereign audit log (not civilian debt).
 *   6. Military cases must be excluded from civilian financial queries.
 */
class MilitaryPipelineTest extends TestCase
{
    use ProstheticTestHelper;

    private function makeMilitaryPricingRequest(CaseRecord $case): PricingRequest
    {
        static $seq = 0;
        $seq++;

        $req = PricingRequest::create([
            'request_no' => "PR-2026-MIL-{$seq}",
            'case_id' => $case->id,
            'patient_type' => 'military',
            'order_ref' => $case->order_ref,
            'patient_name' => 'العقيد محمود خالد',
            'request_date' => now()->toDateString(),
            'status_key' => 'awaiting_admin_approval',
        ]);

        PricingRequestItem::create([
            'pricing_request_id' => $req->id,
            'stock_item_code' => 'RM-001',
            'name' => 'صنف RM-001',
            'qty' => 1,
            'unit_price' => 150.00,
            'line_total' => 150.00,
        ]);

        $case->update(['pricing_request_id' => $req->id]);

        return $req;
    }

    // ── Stage 1: Registration as military ────────────────────────────────────

    public function test_military_patient_registered_with_path_military(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'patient_type' => 'military',
        ]);
    }

    // ── Stage 2-3: Spec page must be financially blind ────────────────────────

    public function test_spec_create_page_does_not_expose_prices_or_stock(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $user = $this->userWithRole('spec');
        $this->actingAs($user);

        $this->stockItem('RM-001', qty: 10, wac: 200.00);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        // The spec create page renders the Blade form — must return 200
        $response = $this->get("/spec/spec/{$case->id}");

        $response->assertOk();

        // The rendered HTML must NOT contain any financial/stock numbers
        // that would compromise the engineer's impartiality
        $html = $response->getContent();
        $this->assertStringNotContainsString('200', $html,
            'WAC value (200) must not appear on the spec page');
        $this->assertStringNotContainsString('wac', strtolower($html),
            '"wac" key must not be exposed on the spec page');
    }

    // ── Stage 4: Pricing bypass → directly to manufacturing ──────────────────

    public function test_military_pricing_bypasses_quote_and_goes_to_manufacturing(): void
    {
        $this->stockItem('RM-001', qty: 10, wac: 150.00);
        app(StockPriceService::class)->addBatch(
            StockItem::first(), 5, 150.00, $this->makeSupplier(), 'INV-001', now()
        );

        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);

        // المعدلات تُغلق → تكلفة صامتة → اعتماد تلقائي → مباشرةً للمخزن (manufacturing/warehouse).
        $case = $this->operationsReadyCase($patient);

        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key,
            'Military case must jump directly to manufacturing without quotes');
        $this->assertEquals(CaseRecord::MFG_WAREHOUSE, $case->manufacturing_stage);
        $this->assertEquals(CaseRecord::PATH_MILITARY, $case->path);
        $this->assertNotNull($case->work_order_no, 'Military path must auto-generate WO on operations approval');
        $this->assertMatchesRegularExpression('/^WO-\d{4}-\d{4}$/', $case->work_order_no);
    }

    /** No quote must exist for the military case */
    public function test_military_pricing_does_not_create_quote(): void
    {
        $this->stockItem('RM-001', qty: 10, wac: 150.00);

        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);

        $case = $this->operationsReadyCase($patient);

        $this->assertDatabaseMissing('quotes', ['case_id' => $case->id]);
    }

    // ── Stage 5: BOM & barcode dispense ──────────────────────────────────────

    public function test_military_bom_dispense_decrements_stock(): void
    {
        $item = $this->stockItem('RM-001', qty: 10);
        app(StockPriceService::class)->addBatch($item, 10, 150.0, $this->makeSupplier(), 'INV-A', now());

        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $user = $this->userWithRole('technical');
        $this->actingAs($user);

        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-MIL-0001']);

        // qty=3 → مطلوب 3 مسحات لنفس الباركود
        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 3],
        ]);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-001', 'BC-RM-001', 'BC-RM-001']);

        $item->refresh();
        $this->assertEquals(7, $item->qty);
    }

    // ── Stage 6: Delivery → sovereign audit log (not civilian debt) ───────────

    public function test_military_delivery_posts_to_sovereign_audit_not_civilian_debt(): void
    {
        $item = $this->stockItem('RM-001', qty: 10);
        app(StockPriceService::class)->addBatch($item, 10, 150.0, $this->makeSupplier(), 'INV-A', now());

        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $techUser = $this->userWithRole('technical');
        $this->actingAs($techUser);

        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update([
            'work_order_no' => 'WO-2026-MIL-0001',
            'total_cost' => 450.00,
        ]);

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 1]]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);
        $this->advanceCaseToFinishing($case);
        $this->finishBomAfterQuality($case->fresh());

        $recepUser = $this->userWithRole('reception');
        $this->actingAs($recepUser);

        app(DeliveryService::class)->close($case->fresh(), $patient->patient_qr);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_DELIVERED, $case->stage_key);

        // Civilian debt table must NOT have been touched
        $civilianDebt = $company->debt()->first();
        $this->assertEquals(0.00, (float) $civilianDebt->fresh()->due,
            'Military delivery must NOT post to civilian ContractCompanyDebt');

        // Military posting writes to audit log with action='post', tag='financial'
        $this->assertDatabaseHas('audit_logs', [
            'tag' => 'financial',
            'action' => 'post',
        ]);
    }

    /** Military case must never be accessible to civilian financial reports */
    public function test_military_case_excluded_from_civilian_finance_query(): void
    {
        $militaryCompany = $this->militaryCompany();
        $militaryPatient = $this->militaryPatient($militaryCompany);
        $this->caseAtStage($militaryPatient, CaseRecord::STAGE_DELIVERED);

        $count = CaseRecord::where('path', CaseRecord::PATH_STANDARD)
            ->where('patient_type', 'civilian')
            ->count();

        $this->assertEquals(0, $count,
            'Military cases must be invisible in civilian-scoped queries');
    }

    /** Civilian case must never inherit military path logic */
    public function test_civilian_case_not_in_military_path(): void
    {
        $civCompany = $this->civilianCompany();
        $civPatient = $this->civilianPatient($civCompany);
        $case = $this->caseAtStage($civPatient, CaseRecord::STAGE_RECEPTION);

        $this->assertEquals(CaseRecord::PATH_STANDARD, $case->path);
        $this->assertNotEquals(CaseRecord::PATH_MILITARY, $case->path);
    }
}
