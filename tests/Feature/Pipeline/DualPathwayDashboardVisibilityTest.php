<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Services\Dashboard\DashboardPageDataService;
use App\Services\Dashboard\DashboardQueueService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Dual-pathway visibility rules (new pipeline):
 *
 * CIVILIAN:  spec → adjustments → cost_calc → quote → operations (decision hub)
 *                                     ↓ OperationsApproved / OCR / QR
 *                              manufacturing (MFG_WAREHOUSE) → technicalBom
 *
 * MILITARY:  spec → adjustments → cost_calc → operations (silent auto-approve)
 *                              → manufacturing (MFG_WAREHOUSE) → technicalBom
 *
 * Dashboard rules under test:
 *   - technicalBom      → ONLY shows BOMs for cases in `manufacturing`
 *   - queueService      → technicalBomRawIds excludes pre-manufacturing BOMs
 *   - queueService      → receptionApprovalPendingCaseIds returns civilian operations cases only
 */
class DualPathwayDashboardVisibilityTest extends TestCase
{
    use ProstheticTestHelper;

    // ── Fixture helper ────────────────────────────────────────────────────────

    private function makeBom(CaseRecord $case, string $bomNo): Bom
    {
        $case->loadMissing('patient');

        return Bom::create([
            'case_id'      => $case->id,
            'bom_no'       => $bomNo,
            'order_ref'    => $case->order_ref,
            'patient_name' => $case->patient?->name ?? 'مريض',
            'stage'        => Bom::STAGE_RAW,
        ]);
    }

    // ── Technical BOM queue ───────────────────────────────────────────────────

    public function test_technical_bom_excludes_civilian_case_in_operations(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $this->makeBom($case, 'BOM-0001');

        $data = app(DashboardPageDataService::class)->resolve('technical', 'bom');
        $ids  = collect($data['warehouse_boms'])->pluck('case_id');

        $this->assertFalse(
            $ids->contains($case->id),
            'حالة مدنية في مكتب التشغيل يجب ألا تظهر في لوحة المخزن قبل اعتماد التشغيل'
        );
    }

    public function test_technical_bom_includes_military_case_in_manufacturing(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0001']);

        $this->makeBom($case, 'BOM-0002');

        $data = app(DashboardPageDataService::class)->resolve('technical', 'bom');
        $ids  = collect($data['warehouse_boms'])->pluck('case_id');

        $this->assertTrue(
            $ids->contains($case->id),
            'حالة عسكرية في manufacturing يجب أن تظهر في لوحة المخزن فوراً'
        );
    }

    public function test_technical_bom_includes_civilian_case_after_ocr_approval(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0002']);

        $this->makeBom($case, 'BOM-0003');

        $data = app(DashboardPageDataService::class)->resolve('technical', 'bom');
        $ids  = collect($data['warehouse_boms'])->pluck('case_id');

        $this->assertTrue(
            $ids->contains($case->id),
            'حالة مدنية بعد مسح الموافقة (manufacturing) يجب أن تظهر في لوحة المخزن'
        );
    }

    // ── Queue service — technicalBomRawIds ────────────────────────────────────

    public function test_queue_service_excludes_raw_bom_for_operations_case(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $bom = $this->makeBom($case, 'BOM-0010');

        $ids = app(DashboardQueueService::class)->technicalBomRawIds();

        $this->assertNotContains((int) $bom->id, $ids);
    }

    public function test_queue_service_includes_raw_bom_for_manufacturing_case(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);

        $bom = $this->makeBom($case, 'BOM-0011');

        $ids = app(DashboardQueueService::class)->technicalBomRawIds();

        $this->assertContains((int) $bom->id, $ids);
    }

    // ── Reception approval-pending queue ─────────────────────────────────────

    /**
     * Civilian waiting_return cases surface via the queue service (feeds the quote page badge).
     * The delivery page no longer carries approval_pending_cases — those live on reception/quote.
     */
    public function test_reception_quote_queue_surfaces_civilian_operations_cases(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $ids = app(DashboardQueueService::class)->receptionApprovalPendingCaseIds();

        $this->assertContains(
            (int) $case->id,
            $ids,
            'حالة مدنية في مكتب التشغيل يجب أن تظهر في طابور الموافقة بالاستقبال'
        );
    }

    public function test_reception_delivery_page_has_no_approval_pending_section(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $data = app(DashboardPageDataService::class)->resolve('reception', 'delivery');

        // The delivery page must only contain delivery_cases (BOM-finished) — no approval queue.
        $this->assertArrayNotHasKey(
            'approval_pending_cases',
            $data,
            'صفحة تسليم المريض يجب ألا تحتوي على قائمة انتظار الموافقة'
        );
    }

    public function test_reception_queue_service_excludes_military_from_approval_pending(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);

        // Military is auto-approved at operations — civilian approval queue must skip them.
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $ids = app(DashboardQueueService::class)->receptionApprovalPendingCaseIds();

        $this->assertNotContains(
            (int) $case->id,
            $ids,
            'الحالات العسكرية يجب ألا تظهر في طابور الموافقة المدني'
        );
    }

    public function test_queue_service_approval_pending_returns_civilian_operations(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $ids = app(DashboardQueueService::class)->receptionApprovalPendingCaseIds();

        $this->assertContains((int) $case->id, $ids);
    }

    public function test_queue_service_approval_pending_excludes_manufacturing_cases(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);

        $ids = app(DashboardQueueService::class)->receptionApprovalPendingCaseIds();

        $this->assertNotContains((int) $case->id, $ids);
    }
}
