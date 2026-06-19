<?php

namespace Tests\Unit;

use App\Enums\WorkflowEvent;
use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\CaseRecord;
use App\Services\WorkflowService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Unit — WorkflowService transition map (محرك التدفق المؤتمت).
 *
 * Every transition must be deterministic: correct event → next stage.
 * Any invalid event must throw InvalidWorkflowTransitionException.
 */
class WorkflowTransitionTest extends TestCase
{
    use ProstheticTestHelper;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = app(WorkflowService::class);
    }

    // ── Happy-path chain ─────────────────────────────────────────────────────

    public function test_exam_approved_moves_to_technical(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_RECEPTION);

        $this->workflow->advance($case, WorkflowEvent::ExamApproved->value);

        $this->assertEquals(CaseRecord::STAGE_TECHNICAL, $case->fresh()->stage_key);
    }

    public function test_spec_saved_moves_to_cost_calc(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        $this->workflow->advance($case, WorkflowEvent::SpecSaved->value);

        $this->assertEquals(CaseRecord::STAGE_COST_CALC, $case->fresh()->stage_key);
    }

    public function test_civilian_pricing_moves_to_waiting_return(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);

        $this->workflow->advance($case, WorkflowEvent::PricingCompletedCivilian->value);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_WAITING_RETURN, $case->stage_key);
        $this->assertNull($case->manufacturing_stage);
    }

    /** الفصل الرابع — المسار العسكري: تخطي كامل مباشرة للتصنيع */
    public function test_military_pricing_bypasses_to_manufacturing(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);

        $this->workflow->advance($case, WorkflowEvent::PricingCompletedMilitary->value);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertEquals(CaseRecord::MFG_WAREHOUSE, $case->manufacturing_stage);
    }

    public function test_approval_scanned_moves_civilian_to_manufacturing(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_WAITING_RETURN);

        $this->workflow->advance($case, WorkflowEvent::ApprovalScanned->value);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertEquals(CaseRecord::MFG_WAREHOUSE, $case->manufacturing_stage);
    }

    public function test_bom_finished_moves_to_ready_delivery(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);

        $this->workflow->advance($case, WorkflowEvent::BomFinished->value);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_READY_DELIVERY, $case->stage_key);
    }

    public function test_delivered_stamps_delivered_at(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_READY_DELIVERY);

        $this->workflow->advance($case, WorkflowEvent::Delivered->value);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_DELIVERED, $case->stage_key);
        $this->assertNotNull($case->delivered_at);
    }

    // ── Guard: invalid transitions throw ─────────────────────────────────────

    public function test_invalid_event_throws_exception(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_RECEPTION);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->workflow->advance($case, WorkflowEvent::BomFinished->value);
    }

    /** BOM_FINISHED must not apply on a waiting_return case */
    public function test_bom_finished_on_wrong_stage_throws(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_WAITING_RETURN);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->workflow->advance($case, WorkflowEvent::BomFinished->value);
    }

    /** Military path must never go through waiting_return */
    public function test_military_never_reaches_waiting_return(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);

        $this->workflow->advance($case, WorkflowEvent::PricingCompletedMilitary->value);

        $this->assertNotEquals(CaseRecord::STAGE_WAITING_RETURN, $case->fresh()->stage_key);
    }

    public function test_audit_log_written_on_transition(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        $this->workflow->advance($case, WorkflowEvent::SpecSaved->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update',
            'tag'    => 'medical',
        ]);
    }
}
