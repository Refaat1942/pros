<?php

namespace Tests\Feature;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Role;
use App\Models\StockDispenseRequest;
use App\Models\User;
use App\Models\WorkshopSection;
use App\Services\BomService;
use App\Services\CostingService;
use App\Services\StockDispenseRequestService;
use App\Services\WorkOrderService;
use App\Services\WorkshopSectionService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class WorkshopInventoryPathwaysTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_workshop_section_crud_and_technician_link(): void
    {
        $admin = $this->userWithRole(Role::SLUG_ADMIN);
        $tech = $this->userWithRole(Role::SLUG_WORKSHOP);

        $section = app(WorkshopSectionService::class)->create(
            ['name' => 'قسم الصب', 'code' => 'casting'],
            [$tech->id],
        );

        $this->assertDatabaseHas('workshop_sections', ['id' => $section->id, 'name' => 'قسم الصب']);
        $this->assertTrue($section->technicians()->whereKey($tech->id)->exists());

        $this->actingAs($admin)
            ->getJson('/admin/workshop-sections/list')
            ->assertOk()
            ->assertJsonFragment(['name' => 'قسم الصب']);
    }

    public function test_operations_approve_assigns_workshop_section_and_technician(): void
    {
        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        $patient = $this->militaryPatient($this->militaryCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);
        $case->update(['work_order_no' => null, 'manufacturing_stage' => null]);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'name' => 'صنف RM-001', 'qty' => 1],
        ]);
        app(WorkOrderService::class)->generate($case->fresh());

        $tech = $this->userWithRole(Role::SLUG_WORKSHOP);
        $section = WorkshopSection::create(['name' => 'تجميع', 'code' => 'assembly', 'sort' => 10, 'active' => true]);
        $section->technicians()->sync([$tech->id]);

        $ops = $this->userWithRole(Role::SLUG_OPERATIONS);

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/approve", [
                'workshop_section_id' => $section->id,
                'assigned_technician_id' => $tech->id,
            ])
            ->assertOk();

        $case->refresh();
        $this->assertSame($section->id, $case->workshop_section_id);
        $this->assertSame($tech->id, $case->assigned_technician_id);
        $this->assertNotNull($case->work_order_no);
        $this->assertSame(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
    }

    public function test_dispense_request_pending_then_approve_executes_movement(): void
    {
        config(['inventory.dispense_requires_approval' => true]);

        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        $patient = $this->militaryPatient($this->militaryCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0099']);

        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'name' => 'صنف RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->reserveForCase($case->fresh());

        $technical = $this->userWithRole(Role::SLUG_TECHNICAL);
        $admin = $this->userWithRole(Role::SLUG_ADMIN);

        $request = app(StockDispenseRequestService::class)->submit(
            $bom,
            ['BC-RM-001'],
            $technical,
        );

        $this->assertSame(StockDispenseRequest::STATUS_PENDING, $request->status);
        $this->assertSame(Bom::STAGE_RAW, $bom->fresh()->stage);

        app(StockDispenseRequestService::class)->approve($request->fresh(), $admin);

        $this->assertSame(Bom::STAGE_WIP, $bom->fresh()->stage);
        $this->assertDatabaseHas('stock_dispense_requests', [
            'id' => $request->id,
            'status' => StockDispenseRequest::STATUS_EXECUTED,
        ]);
    }

    public function test_military_officer_routes_to_services_approval_after_costing(): void
    {
        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        $patient = $this->militaryPatient($this->militaryCompany());
        $patient->update(['military_beneficiary_category' => Patient::BENEFICIARY_OFFICER]);

        $case = $this->costCalcReadyCase($patient);

        app(CostingService::class)->confirmAndIssueQuote($case->fresh(), 'test');

        $case->refresh();
        $this->assertSame(CaseRecord::STAGE_SERVICES_APPROVAL, $case->stage_key);
        $this->assertDatabaseHas('services_approvals', ['case_id' => $case->id, 'status' => 'pending']);
    }

    public function test_services_approval_advances_to_manufacturing_with_work_order(): void
    {
        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        $patient = $this->militaryPatient($this->militaryCompany());
        $patient->update(['military_beneficiary_category' => Patient::BENEFICIARY_FAMILY]);

        $case = $this->costCalcReadyCase($patient);
        app(CostingService::class)->confirmAndIssueQuote($case->fresh(), 'test');
        $case->refresh();

        $admin = $this->userWithRole(Role::SLUG_ADMIN);

        $this->actingAs($admin)
            ->postJson("/admin/services-approvals/{$case->id}/approve")
            ->assertOk();

        $case->refresh();
        $this->assertSame(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertNotNull($case->work_order_no);
    }
}
