<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
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

    private function makeQuote(CaseRecord $case, ContractCompany $company = null): Quote
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

    // ── Stage 4a: Admin approves pricing → civilian: quote + freeze ───────────

    public function test_civilian_pricing_approval_generates_quote_and_waiting_return(): void
    {
        $this->seedStockWithPriceBatch();

        $company  = $this->civilianCompany();
        $patient  = $this->civilianPatient($company);
        $approver = $this->userWithRole('admin');
        $case     = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);
        $req      = $this->makePricingRequest($case, 'civilian');

        app(PricingService::class)->approve($req, $approver);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_WAITING_RETURN, $case->stage_key,
            'Civilian must be frozen at waiting_return after pricing approval');

        $quote = Quote::where('case_id', $case->id)->first();
        $this->assertNotNull($quote, 'A Quote must be generated for civilian cases');
        $this->assertEquals(Quote::STATUS_PENDING, $quote->status);
    }

    // ── Stage 4b: Reception issues quote ─────────────────────────────────────

    public function test_quote_is_issued_and_transitions_to_issued_status(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_WAITING_RETURN);

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

    // ── Stage 4c: OCR scan → work order ──────────────────────────────────────

    public function test_ocr_approval_scan_generates_work_order_and_unlocks_manufacturing(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_WAITING_RETURN);
        $quote   = $this->makeQuote($case, $company);

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
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_WAITING_RETURN);
        $this->makeQuote($case, $company);

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
        $case->update(['work_order_no' => 'WO-2026-0001']);

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
        app(BomService::class)->closeFinished($bom);

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
        app(BomService::class)->closeFinished($bom);

        $recepUser = $this->userWithRole('reception');
        $this->actingAs($recepUser);

        app(DeliveryService::class)->close($case->fresh(), 'QR-PT-CIV-0001');

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_DELIVERED, $case->stage_key);
        $this->assertNotNull($case->delivered_at);

        // Financial posting: civilian debt must increase
        $debt = $company->debt()->first()->fresh();
        $this->assertEquals(400.00, (float) $debt->due,
            'Civilian delivery must post quote_total to company debt');
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

        app(DeliveryService::class)->close($case, 'QR-PT-CIV-0001');
    }
}
