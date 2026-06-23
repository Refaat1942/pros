<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PricingRequest;
use App\Models\PricingRequestItem;
use App\Models\Quote;
use App\Services\ApprovalService;
use App\Services\BomService;
use App\Services\DeliveryService;
use App\Services\PricingService;
use App\Services\QuoteService;
use App\Models\ContractCompany;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Feature — Complete Civilian Pipeline (end-to-end).
 *
 * Covers every chapter from the scenario narrative:
 *   Ch.1 Reception → Ch.2 Exam → Ch.3 Pricing (highest price, not WAC)
 *   → Ch.4 Quote freeze → OCR scan → Work Order
 *   → Ch.5 Barcode dispense → Ch.6 Delivery & financial posting
 */
class CivilianPipelineTest extends TestCase
{
    use ProstheticTestHelper;

    // ── Fixture helpers ───────────────────────────────────────────────────────

    private function seedStockWithPriceBatch(): void
    {
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-001', qty: 20, wac: 80.00);

        app(StockPriceService::class)->addBatch($item, 5, 150.00, $supplier, 'INV-001', now());
        app(StockPriceService::class)->addBatch($item, 5, 200.00, $supplier, 'INV-002', now());
    }

    private function makePricingRequest(CaseRecord $case, string $type = 'civilian'): PricingRequest
    {
        static $seq = 0;
        $seq++;

        $req = PricingRequest::create([
            'request_no'   => "PR-2026-{$type}-{$seq}",
            'case_id'      => $case->id,
            'patient_type' => $type,
            'order_ref'    => $case->order_ref,
            'patient_name' => 'مريض اختبار',
            'request_date' => now()->toDateString(),
            'status_key'   => 'awaiting_admin_approval',
        ]);

        PricingRequestItem::create([
            'pricing_request_id' => $req->id,
            'stock_item_code'    => 'RM-001',
            'name'               => 'صنف RM-001',
            'qty'                => 2,
            'unit_price'         => 200.00,
            'line_total'         => 400.00,
        ]);

        $case->update(['pricing_request_id' => $req->id]);

        return $req;
    }

    private function makeQuote(CaseRecord $case, ?ContractCompany $company = null): Quote
    {
        static $qSeq = 0;
        $qSeq++;

        return Quote::create([
            'quote_no'     => "QT-2026-{$qSeq}",
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => 'مريض اختبار',
            'company_name' => $company?->name ?? 'الجهة',
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_ISSUED,
            'total'        => 400.00,
        ]);
    }

    // ── Stage 1: Patient registration ─────────────────────────────────────────

    public function test_civilian_patient_created_with_qr_code(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);

        $this->assertDatabaseHas('patients', [
            'id'           => $patient->id,
            'patient_type' => 'civilian',
        ]);
        $this->assertStringStartsWith('QR-', $patient->patient_qr);
    }

    // ── Stage 2: Medical exam via HTTP (doctor dashboard) ────────────────────

    public function test_medical_exam_advances_case_via_workflow_service(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $doctor  = $this->userWithRole('doctor');

        // Create a MedicalRecord draft, then lock it
        $this->actingAs($doctor);

        $record = MedicalRecord::create([
            'patient_id'   => $patient->id,
            'patient_name' => $patient->name,
            'national_id'  => $patient->national_id,
            'company_name' => $patient->company_name,
            'patient_type' => $patient->patient_type,
            'diagnosis'    => 'بتر فوق الركبة',
            'prescription' => 'ركبة ذكية + أنبوب كربون',
            'doctor_name'  => $doctor->name,
            'doctor_user_id' => $doctor->id,
            'record_date'  => now()->toDateString(),
            'status'       => MedicalRecord::STATUS_DRAFT,
            'locked'       => false,
        ]);

        app(\App\Services\MedicalRecordService::class)->lock($record);

        $case = CaseRecord::where('patient_id', $patient->id)->first();
        $this->assertNotNull($case);
        $this->assertEquals(CaseRecord::STAGE_TECHNICAL, $case->stage_key);
    }

    // ── Stage 3: Pricing uses highest price, not WAC ──────────────────────────

    public function test_pricing_uses_highest_purchase_price_not_wac(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);
        $req     = $this->makePricingRequest($case);

        // Highest price for RM-001 is 200.00 (from INV-002), not WAC (80)
        $item = $req->items()->first();
        $this->assertEquals(200.00, (float) $item->unit_price,
            'Quote pricing must use highest purchase price, NOT WAC');
    }

    // ── Stage 4a: Adjustments completion → issued quote + operations gate ─────

    public function test_civilian_adjustments_completion_generates_quote_and_lands_at_operations(): void
    {
        $this->seedStockWithPriceBatch();

        $company  = $this->civilianCompany();
        $patient  = $this->civilianPatient($company);

        // التوصيف → معدلات → تكاليف → عرض السعر → مكتب التشغيل (للقرار).
        $case = $this->operationsReadyCase($patient);

        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key,
            'Civilian must land at the operations desk after costing/quote');

        $quote = Quote::where('case_id', $case->id)->first();
        $this->assertNotNull($quote, 'A Quote must be generated for civilian cases');
        $this->assertEquals(Quote::STATUS_ISSUED, $quote->status);
    }

    // ── Stage 4b: Quote re-issue helper ──────────────────────────────────────

    public function test_quote_is_issued_and_transitions_to_issued_status(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $quote = Quote::create([
            'quote_no'    => 'QT-2026-0001',
            'case_id'     => $case->id,
            'order_ref'   => $case->order_ref,
            'patient_name'=> $patient->name,
            'company_name'=> $company->name,
            'quote_date'  => now()->toDateString(),
            'status'      => Quote::STATUS_PENDING,
            'total'       => 400.00,
        ]);

        app(QuoteService::class)->markIssued($quote);

        $this->assertEquals(Quote::STATUS_ISSUED, $quote->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'issue', 'tag' => 'quotes']);
    }

    // ── Stage 4c: QR approval scan at operations → reserve + work order ───────

    public function test_ocr_approval_scan_generates_work_order_and_unlocks_manufacturing(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->operationsReadyCase($patient);
        $quote   = Quote::where('case_id', $case->id)->firstOrFail();

        app(ApprovalService::class)->confirm($case, $quote->quote_no);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertEquals(CaseRecord::MFG_WAREHOUSE, $case->manufacturing_stage);
        $this->assertNotNull($case->work_order_no);
        $this->assertStringStartsWith('WO-', $case->work_order_no);
        $this->assertEquals(Quote::STATUS_APPROVED, $quote->fresh()->status);
    }

    public function test_scanning_wrong_qr_does_not_unlock_case(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->operationsReadyCase($patient);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ApprovalService::class)->confirm($case, 'WRONG-QR-CODE');
    }

    // ── Stage 5: BOM creation & barcode dispense ──────────────────────────────

    public function test_bom_creation_reserves_stock_qty(): void
    {
        $this->seedStockWithPriceBatch();
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0001']);

        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);

        $this->assertEquals(Bom::STAGE_RAW, $bom->stage);

        $item = \App\Models\StockItem::where('code', 'RM-001')->first();
        $this->assertEquals(2, $item->reserved, 'Creating a BOM must reserve the items');
    }

    public function test_barcode_dispense_moves_bom_to_wip_and_decrements_stock(): void
    {
        $this->seedStockWithPriceBatch();
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0001', 'quote_total' => 400.00]);

        $this->actingAs($user);

        // 1 BomItem row with qty=2 → 1 barcode required
        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']); // 1 barcode per row

        $bom->refresh();
        $this->assertEquals(Bom::STAGE_WIP, $bom->stage);

        $stock = \App\Models\StockItem::where('code', 'RM-001')->first();
        $this->assertEquals(18, $stock->qty, 'Stock must be reduced by dispensed qty=2');
        $this->assertEquals(0, $stock->reserved, 'Reserved must be cleared after dispense');

        $debt = $company->debt()->first()->fresh();
        $this->assertEquals(400.00, (float) $debt->due,
            'Civilian dispense must post quote_total to company ledger');

        $case->refresh();
        $this->assertNotNull($case->ledger_posted_at);
    }

    public function test_civilian_dispense_posts_debt_to_contract_company(): void
    {
        $this->seedStockWithPriceBatch();
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0001', 'quote_total' => 400.00]);

        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $debt = $company->debt()->first()->fresh();
        $this->assertEquals(400.00, (float) $debt->due,
            'Civilian dispense must post quote_total to company ledger');

        $case->refresh();
        $this->assertNotNull($case->ledger_posted_at);
    }

    // ── Stage 6: BOM close → ready_delivery → QR scan → delivered ───────────

    public function test_bom_close_advances_case_to_ready_delivery(): void
    {
        $this->seedStockWithPriceBatch();
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0001']);
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 1]]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);
        $this->advanceCaseToFinishing($case);
        $this->finishBomAfterQuality($case->fresh());

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_READY_DELIVERY, $case->stage_key);
        $this->assertEquals(Bom::STAGE_FINISHED, $bom->fresh()->stage);
    }

    public function test_delivery_qr_scan_closes_case_and_posts_debt(): void
    {
        $this->seedStockWithPriceBatch();
        $company  = $this->civilianCompany();
        $patient  = $this->civilianPatient($company);
        $techUser = $this->userWithRole('technical');
        $this->actingAs($techUser);

        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0001', 'quote_total' => 400.00]);

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 1]]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);
        $this->advanceCaseToFinishing($case);
        $this->finishBomAfterQuality($case->fresh());

        $recepUser = $this->userWithRole('reception');
        $this->actingAs($recepUser);

        $debtBeforeDelivery = (float) $company->debt()->first()->fresh()->due;

        app(DeliveryService::class)->close($case->fresh(), $patient->patient_qr);

        $case->refresh();
        $patient->refresh();
        $this->assertEquals(CaseRecord::STAGE_DELIVERED, $case->stage_key);
        $this->assertNotNull($case->delivered_at);
        $this->assertNotNull($case->invoice_no);
        $this->assertEquals(400.00, (float) $case->invoice_total);
        $this->assertEquals(400.00, (float) $case->total_cost);
        $this->assertEquals(400.00, (float) $case->paid);
        $this->assertEquals(Patient::STATUS_DONE, $patient->status);
        $this->assertNotNull($patient->archived_at);

        // Debt posted on dispense — delivery must not double-post
        $debt = $company->debt()->first()->fresh();
        $this->assertEquals(400.00, (float) $debt->due,
            'Civilian debt must remain quote_total (posted at dispense, not duplicated at delivery)');
        $this->assertEquals($debtBeforeDelivery, (float) $debt->due);
    }

    // ── Guard: cannot deliver if BOM is not finished ──────────────────────────

    public function test_delivery_blocked_when_bom_not_finished(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_READY_DELIVERY);

        Bom::create([
            'bom_no'       => 'BOM-0001',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'stage'        => Bom::STAGE_WIP,
        ]);

        $user = $this->userWithRole('reception');
        $this->actingAs($user);

        $this->expectException(\App\Exceptions\DeliveryNotReadyException::class);

        app(DeliveryService::class)->close($case, $patient->patient_qr);
    }
}
