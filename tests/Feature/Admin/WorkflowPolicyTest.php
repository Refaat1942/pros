<?php

namespace Tests\Feature\Admin;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\WorkflowStagePolicy;
use App\Services\BomService;
use App\Services\WorkflowPolicyService;
use App\Services\WorkflowService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class WorkflowPolicyTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_save_workflow_policies(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->putJson(route('admin.workflow-policies.update'), [
                'pathway' => WorkflowStagePolicy::PATHWAY_CIVILIAN,
                'policies' => [
                    [
                        'stage_key' => CaseRecord::STAGE_EXAM,
                        'label' => 'الكشف',
                        'sort' => 2,
                        'required' => false,
                        'auto_skip' => false,
                        'skip_roles' => ['doctor'],
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('workflow_stage_policies', [
            'pathway' => WorkflowStagePolicy::PATHWAY_CIVILIAN,
            'stage_key' => CaseRecord::STAGE_EXAM,
            'required' => false,
        ]);
    }

    public function test_locked_stages_cannot_be_marked_optional(): void
    {
        $service = app(WorkflowPolicyService::class);

        $service->savePolicies(WorkflowStagePolicy::PATHWAY_CIVILIAN, [
            [
                'stage_key' => CaseRecord::STAGE_QUOTE,
                'label' => 'عرض',
                'sort' => 6,
                'required' => false,
                'auto_skip' => true,
                'skip_roles' => ['admin'],
            ],
        ]);

        $policies = $service->policies(WorkflowStagePolicy::PATHWAY_CIVILIAN);
        $quote = collect($policies)->firstWhere('stage_key', CaseRecord::STAGE_QUOTE);

        $this->assertTrue($quote['required']);
        $this->assertFalse($quote['auto_skip']);
        $this->assertTrue($quote['locked']);
    }

    public function test_military_auto_skip_adjustments_after_spec_saved(): void
    {
        $this->stockItem('RM-001', qty: 10);

        app(WorkflowPolicyService::class)->resetToDefaults(WorkflowStagePolicy::PATHWAY_MILITARY);

        $patient = $this->militaryPatient($this->militaryCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        app(WorkflowService::class)->advance($case, WorkflowEvent::SpecSaved->value);

        $this->assertSame(CaseRecord::STAGE_COST_CALC, $case->fresh()->stage_key);
    }

    public function test_admin_can_skip_adjustments_for_civilian_case(): void
    {
        $this->stockItem('RM-001', qty: 10);

        $admin = $this->userWithRole('admin');
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.cases.workflow.skip', $case))
            ->assertOk()
            ->assertJsonPath('case.stage_key', CaseRecord::STAGE_COST_CALC);
    }
}
