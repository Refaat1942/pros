<?php

namespace Tests\Feature\Admin;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\PathwayStep;
use App\Services\PathwayConfigService;
use App\Services\PathwayTransitionMessageService;
use App\Services\WorkflowPolicyService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * ما يُعدَّل في مصمم المسار يُطبَّق فعلاً — على كل مسار (نقدي، جهات، عسكري، خدمات عسكرية).
 */
class PathwaySettingsEffectTest extends TestCase
{
    use ProstheticTestHelper;

    /** يحفظ تعديلاً على خطوة عبر نفس طلب شاشة المصمم. */
    private function editStep(string $pathway, string $key, array $changes): void
    {
        $steps = array_map(function (array $step) use ($key, $changes) {
            $row = array_intersect_key($step, array_flip([
                'key', 'label', 'sort', 'stage_keys', 'active', 'description', 'owner_department',
                'action_summary', 'next_step_key', 'required', 'auto_skip', 'skip_roles', 'handlers',
            ]));

            return $step['key'] === $key ? array_merge($row, $changes) : $row;
        }, app(PathwayConfigService::class)->steps($pathway));

        $this->actingAs($this->userWithRole('admin'))
            ->putJson(route('admin.pathway-settings.update'), ['pathway' => $pathway, 'steps' => $steps])
            ->assertOk();
    }

    private function entityCase(string $stage): CaseRecord
    {
        $company = $this->civilianCompany();
        $case = $this->caseAtStage($this->civilianPatient($company), $stage);
        $case->update(['contract_company_id' => $company->id]);

        return $case->fresh('patient');
    }

    public function test_renamed_step_label_appears_in_transfer_messages(): void
    {
        $this->editStep(PathwayStep::PATHWAY_ENTITY, 'cost_calc', ['label' => 'مراجعة التكلفة المخصصة']);

        $case = $this->entityCase(CaseRecord::STAGE_COST_CALC);
        $message = app(PathwayTransitionMessageService::class)->transferMessage(
            $case, WorkflowEvent::AdjustmentsCompleted->value, CaseRecord::STAGE_ADJUSTMENTS,
        );

        $this->assertStringContainsString('مراجعة التكلفة المخصصة', $message);
    }

    public function test_entity_pathway_skip_rules_apply_to_entity_cases_only(): void
    {
        $this->editStep(PathwayStep::PATHWAY_ENTITY, 'adjustments', ['required' => false, 'auto_skip' => true]);

        $policies = app(WorkflowPolicyService::class);
        $entity = $this->entityCase(CaseRecord::STAGE_ADJUSTMENTS);
        $cash = $this->caseAtStage($this->cashPatient(), CaseRecord::STAGE_ADJUSTMENTS)->fresh('patient');

        $this->assertSame(PathwayStep::PATHWAY_ENTITY, $policies->pathwayForCase($entity));
        $this->assertTrue($policies->shouldAutoSkip($entity), 'entity pathway edit applies to entity case');
        $this->assertFalse($policies->shouldAutoSkip($cash), 'cash case keeps its own (civilian) rules');
        $this->assertContains(CaseRecord::STAGE_ADJUSTMENTS, $policies->skippableStageKeys($policies->pathwayForCase($entity)));
    }

    public function test_skip_roles_from_designer_allow_that_role_to_skip(): void
    {
        $this->editStep(PathwayStep::PATHWAY_ENTITY, 'adjustments', [
            'required' => false, 'auto_skip' => false, 'skip_roles' => ['adjustments'],
        ]);

        $policies = app(WorkflowPolicyService::class);
        $case = $this->entityCase(CaseRecord::STAGE_ADJUSTMENTS);

        $this->assertTrue($policies->canManualSkip($case, CaseRecord::STAGE_ADJUSTMENTS, $this->userWithRole('adjustments')));
        $this->assertFalse($policies->canManualSkip($case, CaseRecord::STAGE_ADJUSTMENTS, $this->userWithRole('spec')));
    }

    public function test_required_step_cannot_be_skipped(): void
    {
        $this->editStep(PathwayStep::PATHWAY_ENTITY, 'adjustments', ['required' => true, 'auto_skip' => true]);

        $policies = app(WorkflowPolicyService::class);
        $case = $this->entityCase(CaseRecord::STAGE_ADJUSTMENTS);

        $this->assertFalse($policies->shouldAutoSkip($case));
        $this->assertFalse($policies->canManualSkip($case, CaseRecord::STAGE_ADJUSTMENTS, $this->userWithRole('adjustments')));
    }

    public function test_deactivated_step_is_hidden_from_patient_tracking(): void
    {
        $config = app(PathwayConfigService::class);
        $before = array_column($config->displayStepsForPathway(PathwayStep::PATHWAY_ENTITY), 'key');
        $this->assertContains('adjustments', $before);

        $this->editStep(PathwayStep::PATHWAY_ENTITY, 'adjustments', ['active' => false]);

        $after = array_column($config->displayStepsForPathway(PathwayStep::PATHWAY_ENTITY), 'key');
        $this->assertNotContains('adjustments', $after);
        $this->assertContains('adjustments', array_column($config->displayStepsForPathway(PathwayStep::PATHWAY_CIVILIAN), 'key'),
            'other pathways are not affected');
    }

    public function test_admin_case_skip_response_uses_the_cases_real_pathway(): void
    {
        $this->editStep(PathwayStep::PATHWAY_ENTITY, 'adjustments', ['required' => false, 'auto_skip' => false]);

        $case = $this->entityCase(CaseRecord::STAGE_ADJUSTMENTS);

        $this->assertContains(
            CaseRecord::STAGE_ADJUSTMENTS,
            app(WorkflowPolicyService::class)->skippableStageKeys(app(WorkflowPolicyService::class)->pathwayForCase($case)),
        );

        // والعكس: جعلها إلزامية في مسار الجهات يُطبَّق على حالة الجهة.
        $this->editStep(PathwayStep::PATHWAY_ENTITY, 'adjustments', ['required' => true]);
        $this->assertNotContains(
            CaseRecord::STAGE_ADJUSTMENTS,
            app(WorkflowPolicyService::class)->skippableStageKeys(app(WorkflowPolicyService::class)->pathwayForCase($case)),
        );
    }
}
