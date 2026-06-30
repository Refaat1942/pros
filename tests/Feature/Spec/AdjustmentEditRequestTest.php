<?php

namespace Tests\Feature\Spec;

use App\Enums\SpecEditRequestSource;
use App\Enums\SpecEditRequestStatus;
use App\Models\AppNotification;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\Role;
use App\Models\SpecEditRequest;
use App\Models\TechOrderSpec;
use App\Services\AdjustmentsService;
use App\Services\BomService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdjustmentEditRequestTest extends TestCase
{
    use ProstheticTestHelper;

    private function caseInCostCalc(): CaseRecord
    {
        $this->stockItem('RM-ADJ-A', qty: 20);
        $this->stockItem('RM-ADJ-B', qty: 20);

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        TechOrderSpec::create([
            'order_ref'    => $case->order_ref,
            'case_id'      => $case->id,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'locked'       => true,
        ]);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-ADJ-A', 'qty' => 1],
        ]);

        app(AdjustmentsService::class)->addItems($case, [
            ['stock_item_code' => 'RM-ADJ-B', 'name' => 'صنف B', 'qty' => 2],
        ]);

        return app(AdjustmentsService::class)->complete($case->fresh());
    }

    public function test_adjustments_can_submit_edit_request_in_cost_calc(): void
    {
        $case = $this->caseInCostCalc();
        $user = $this->userWithRole('adjustments');

        $this->actingAs($user)
            ->postJson(route('adjustments.adjustments.edit-request.store', $case), [
                'items' => [
                    ['stock_item_code' => 'RM-ADJ-B', 'name' => 'صنف B', 'qty' => 3],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('request.status', SpecEditRequestStatus::Pending->value)
            ->assertJsonPath('request.source', SpecEditRequestSource::Adjustments->value);

        $this->assertDatabaseHas('spec_edit_requests', [
            'case_id' => $case->id,
            'source'  => SpecEditRequestSource::Adjustments->value,
            'status'  => SpecEditRequestStatus::Pending->value,
        ]);

        $this->assertSame(
            1,
            AppNotification::forRole(Role::SLUG_ADMIN)->where('event', 'spec_edit_requested')->count()
        );
    }

    public function test_admin_approve_adjustment_edit_updates_bom(): void
    {
        $case = $this->caseInCostCalc();
        $adjUser = $this->userWithRole('adjustments');

        $this->actingAs($adjUser)
            ->postJson(route('adjustments.adjustments.edit-request.store', $case), [
                'items' => [
                    ['stock_item_code' => 'RM-ADJ-B', 'name' => 'صنف B', 'qty' => 5],
                ],
            ])
            ->assertCreated();

        $request = SpecEditRequest::where('case_id', $case->id)->firstOrFail();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.spec-edit-requests.approve', $request))
            ->assertOk()
            ->assertJsonPath('request.status', SpecEditRequestStatus::Approved->value);

        $qty = BomItem::whereHas('bom', fn ($q) => $q->where('case_id', $case->id))
            ->where('source', BomItem::SOURCE_ADJUSTMENT)
            ->where('stock_item_code', 'RM-ADJ-B')
            ->value('qty');

        $this->assertSame(5, (int) $qty);

        $this->assertSame(
            1,
            AppNotification::forRole(Role::SLUG_ADJUSTMENTS)->where('event', 'spec_edit_approved')->count()
        );
    }

    public function test_complete_blocked_while_pending_adjustment_edit(): void
    {
        $this->stockItem('RM-ADJ-A', qty: 20);

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        TechOrderSpec::create([
            'order_ref'    => $case->order_ref,
            'case_id'      => $case->id,
            'patient_name' => $patient->name,
            'locked'       => true,
        ]);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-ADJ-A', 'qty' => 1],
        ]);

        SpecEditRequest::create([
            'source'               => SpecEditRequestSource::Adjustments,
            'tech_order_spec_id'   => $case->techOrderSpec()->firstOrFail()->id,
            'case_id'              => $case->id,
            'requested_by_user_id' => $this->userWithRole('adjustments')->id,
            'status'               => SpecEditRequestStatus::Pending,
            'original_items'       => [],
            'proposed_items'       => [],
        ]);

        $user = $this->userWithRole('adjustments');

        $this->actingAs($user)
            ->postJson(route('adjustments.adjustments.complete', $case))
            ->assertStatus(422);
    }

    public function test_costing_confirm_blocked_while_pending_edit(): void
    {
        $case = $this->caseInCostCalc();
        $adjUser = $this->userWithRole('adjustments');

        $this->actingAs($adjUser)
            ->postJson(route('adjustments.adjustments.edit-request.store', $case), [
                'items' => [
                    ['stock_item_code' => 'RM-ADJ-B', 'name' => 'صنف B', 'qty' => 2],
                ],
            ])
            ->assertCreated();

        $costing = $this->userWithRole('costing');

        $this->actingAs($costing)
            ->postJson(route('costing.queue.confirm', $case))
            ->assertStatus(422);
    }
}
