<?php

namespace Tests\Unit;

use App\Enums\PricingRequestStatus;
use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Models\PricingRequestItem;
use App\Services\BomService;
use App\Services\PricingService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Unit — PricingRequestStatus badge pipeline
 *
 * هذا الاختبار يضمن أن حقل status_key في جدول pricing_requests
 * يحتوي على القيم الأربعة بالضبط التي تتوقعها الـ Badges في الـ Prototype:
 *
 *   processing            → badge-info    (جاري الاحتساب)
 *   awaiting_admin_approval → badge-warning (بانتظار الاعتماد)
 *   sent_to_reception     → badge-success (تم الإرسال للاستقبال)
 *   insufficient          → badge-danger  (غير كافٍ)
 *
 * كل اختبار يتحقق من قيمة الـ DB مباشرة وليس من label text فقط.
 */
class PricingStatusTransitionTest extends TestCase
{
    use ProstheticTestHelper;

    // ── Enum values ──────────────────────────────────────────────────────────

    public function test_processing_value_is_exact_string(): void
    {
        $this->assertEquals('processing', PricingRequestStatus::Processing->value);
    }

    public function test_awaiting_admin_approval_value_is_exact_string(): void
    {
        $this->assertEquals('awaiting_admin_approval', PricingRequestStatus::AwaitingAdminApproval->value);
    }

    public function test_sent_to_reception_value_is_exact_string(): void
    {
        $this->assertEquals('sent_to_reception', PricingRequestStatus::SentToReception->value);
    }

    public function test_insufficient_value_is_exact_string(): void
    {
        $this->assertEquals('insufficient', PricingRequestStatus::Insufficient->value);
    }

    // ── Badge CSS class mapping ───────────────────────────────────────────────

    public function test_badge_classes_match_prototype_design(): void
    {
        $this->assertEquals('badge-info',    PricingRequestStatus::Processing->badgeClass());
        $this->assertEquals('badge-warning', PricingRequestStatus::AwaitingAdminApproval->badgeClass());
        $this->assertEquals('badge-success', PricingRequestStatus::SentToReception->badgeClass());
        $this->assertEquals('badge-danger',  PricingRequestStatus::Insufficient->badgeClass());
    }

    // ── Arabic labels match prototype text ───────────────────────────────────

    public function test_arabic_labels_match_prototype_ui_text(): void
    {
        $this->assertEquals('جاري الاحتساب',          PricingRequestStatus::Processing->label());
        $this->assertEquals('بانتظار الاعتماد',        PricingRequestStatus::AwaitingAdminApproval->label());
        $this->assertEquals('تم الإرسال للاستقبال',    PricingRequestStatus::SentToReception->label());
        $this->assertEquals('غير كافٍ',                PricingRequestStatus::Insufficient->label());
    }

    // ── DB value written on calculate() → processing → awaiting_admin_approval

    public function test_calculate_writes_processing_then_awaiting_approval_in_db(): void
    {
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-001', qty: 10);
        app(StockPriceService::class)->addBatch($item, 10, 200.00, $supplier, 'INV-A', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);

        // Start with processing (as SpecService does before calling calculate)
        $request = PricingRequest::create([
            'request_no'   => 'PR-TEST-001',
            'case_id'      => $case->id,
            'patient_type' => 'civilian',
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'request_date' => now()->toDateString(),
            'status_key'   => PricingRequestStatus::Processing->value,
        ]);

        PricingRequestItem::create([
            'pricing_request_id' => $request->id,
            'stock_item_code'    => 'RM-001',
            'name'               => 'صنف RM-001',
            'qty'                => 1,
        ]);

        // Verify DB shows 'processing' before calculate()
        $this->assertDatabaseHas('pricing_requests', [
            'id'         => $request->id,
            'status_key' => 'processing',
        ]);

        app(PricingService::class)->calculate($request);

        // After calculate() → DB must show 'awaiting_admin_approval'
        $this->assertDatabaseHas('pricing_requests', [
            'id'         => $request->id,
            'status_key' => 'awaiting_admin_approval',
        ]);

        $this->assertDatabaseMissing('pricing_requests', [
            'id'         => $request->id,
            'status_key' => 'processing',
        ]);
    }

    // ── DB value written on operations approval → sent_to_reception ──────────

    public function test_operations_approval_writes_sent_to_reception_in_db(): void
    {
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-001', qty: 10);
        app(StockPriceService::class)->addBatch($item, 10, 200.00, $supplier, 'INV-B', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->operationsReadyCase($patient);

        app(\App\Services\OperationsService::class)->approve($case, 'مكتب التشغيل');

        // DB must show 'sent_to_reception'
        $this->assertDatabaseHas('pricing_requests', [
            'case_id'    => $case->id,
            'status_key' => 'sent_to_reception',
        ]);
    }

    // ── Guard: operations approval rejects cases not at the operations gate ────

    public function test_operations_approval_rejects_case_at_cost_calc(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(\App\Services\OperationsService::class)->approve($case, 'مكتب التشغيل');
    }

    public function test_operations_approval_rejects_case_at_technical(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(\App\Services\OperationsService::class)->approve($case, 'مكتب التشغيل');
    }

    // ── DB value written when BOM stock check fails → insufficient ────────────

    public function test_bom_creation_with_insufficient_stock_sets_insufficient_in_db(): void
    {
        // Stock with only 1 unit available, but BOM requests 5
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-001', qty: 1);
        app(StockPriceService::class)->addBatch($item, 1, 200.00, $supplier, 'INV-C', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('technical');
        $this->actingAs($user);

        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-INS-001']);

        // Link a PricingRequest so BomService can mark it insufficient
        $request = PricingRequest::create([
            'request_no'   => 'PR-TEST-INS',
            'case_id'      => $case->id,
            'patient_type' => 'civilian',
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'request_date' => now()->toDateString(),
            'status_key'   => PricingRequestStatus::SentToReception->value,
        ]);
        $case->update(['pricing_request_id' => $request->id]);

        try {
            app(BomService::class)->create($case, [
                ['stock_item_code' => 'RM-001', 'qty' => 5],  // only 1 available
            ]);
            $this->fail('Expected HttpException for insufficient stock');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Expected — verify DB badge state is 'insufficient'
            $this->assertDatabaseHas('pricing_requests', [
                'id'         => $request->id,
                'status_key' => 'insufficient',
            ]);

            // Audit log must record the failure
            $this->assertDatabaseHas('audit_logs', [
                'action' => 'insufficient',
                'tag'    => 'pricing',
            ]);
        }
    }

    // ── isApprovable() guards ─────────────────────────────────────────────────

    public function test_only_awaiting_admin_approval_is_approvable(): void
    {
        $this->assertFalse(PricingRequestStatus::Processing->isApprovable());
        $this->assertTrue(PricingRequestStatus::AwaitingAdminApproval->isApprovable());
        $this->assertFalse(PricingRequestStatus::SentToReception->isApprovable());
        $this->assertFalse(PricingRequestStatus::Insufficient->isApprovable());
    }

    // ── Complete lifecycle in sequence ────────────────────────────────────────

    public function test_full_status_lifecycle_processing_to_sent_to_reception(): void
    {
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-001', qty: 10);
        app(StockPriceService::class)->addBatch($item, 10, 200.00, $supplier, 'INV-D', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);

        // المعدلات تُغلق فتُنشأ التكلفة وتُحتسب → awaiting_admin_approval، والحالة تصل مكتب التشغيل.
        $case = $this->operationsReadyCase($patient);
        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);
        $this->assertDatabaseHas('pricing_requests', [
            'case_id'    => $case->id,
            'status_key' => 'awaiting_admin_approval',
        ]);

        // اعتماد مكتب التشغيل → sent_to_reception
        app(\App\Services\OperationsService::class)->approve($case, 'مكتب التشغيل');
        $this->assertDatabaseHas('pricing_requests', [
            'case_id'    => $case->id,
            'status_key' => 'sent_to_reception',
        ]);
    }
}
