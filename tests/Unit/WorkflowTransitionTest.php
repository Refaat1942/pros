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
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_RECEPTION);

        $this->workflow->advance($case, WorkflowEvent::ExamApproved->value);

        $this->assertEquals(CaseRecord::STAGE_TECHNICAL, $case->fresh()->stage_key);
    }

    public function test_spec_saved_moves_to_adjustments(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        $this->workflow->advance($case, WorkflowEvent::SpecSaved->value);

        $this->assertEquals(CaseRecord::STAGE_ADJUSTMENTS, $case->fresh()->stage_key);
    }

    public function test_adjustments_completed_moves_to_cost_calc(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        $this->workflow->advance($case, WorkflowEvent::AdjustmentsCompleted->value);

        $this->assertEquals(CaseRecord::STAGE_COST_CALC, $case->fresh()->stage_key);
    }

    public function test_costing_completed_moves_to_quote(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);

        $this->workflow->advance($case, WorkflowEvent::CostingCompleted->value);

        $this->assertEquals(CaseRecord::STAGE_QUOTE, $case->fresh()->stage_key);
    }

    public function test_quote_issued_moves_to_operations(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_QUOTE);

        $this->workflow->advance($case, WorkflowEvent::QuoteIssued->value);

        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->fresh()->stage_key);
    }

    public function test_operations_approved_moves_to_manufacturing_warehouse(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $this->workflow->advance($case, WorkflowEvent::OperationsApproved->value);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertEquals(CaseRecord::MFG_WAREHOUSE, $case->manufacturing_stage);
    }

    public function test_operations_can_return_to_adjustments(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $this->workflow->advance($case, WorkflowEvent::ReturnedToAdjustments->value);

        $this->assertEquals(CaseRecord::STAGE_ADJUSTMENTS, $case->fresh()->stage_key);
    }

    public function test_operations_can_return_to_technical(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $this->workflow->advance($case, WorkflowEvent::ReturnedToTechnical->value);

        $this->assertEquals(CaseRecord::STAGE_TECHNICAL, $case->fresh()->stage_key);
    }

    public function test_bom_dispensed_from_manufacturing_warehouse_sets_issue(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);

        $this->workflow->advance($case, WorkflowEvent::BomDispensed->value);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertEquals(CaseRecord::MFG_ISSUE, $case->manufacturing_stage);
    }

    public function test_bom_finished_moves_to_ready_delivery(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);

        $this->workflow->advance($case, WorkflowEvent::BomFinished->value);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_READY_DELIVERY, $case->stage_key);
    }

    public function test_delivered_stamps_delivered_at(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_READY_DELIVERY);

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
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_RECEPTION);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->workflow->advance($case, WorkflowEvent::BomFinished->value);
    }

    /** BOM_FINISHED must not apply on an operations-stage case */
    public function test_bom_finished_on_wrong_stage_throws(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->workflow->advance($case, WorkflowEvent::BomFinished->value);
    }

    /** OperationsApproved must not skip the operations gate. */
    public function test_operations_approved_on_wrong_stage_throws(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->workflow->advance($case, WorkflowEvent::OperationsApproved->value);
    }

    public function test_audit_log_written_on_transition(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        $this->workflow->advance($case, WorkflowEvent::SpecSaved->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update',
            'tag' => 'medical',
        ]);
    }
}
