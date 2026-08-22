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
use App\Services\WorkshopTechnicianService;
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

    public function test_workshop_technician_crud_via_api(): void
    {
        $admin = $this->userWithRole(Role::SLUG_ADMIN);
        $this->userWithRole(Role::SLUG_WORKSHOP);
        $section = WorkshopSection::create(['name' => 'تجميع', 'code' => 'assembly', 'sort' => 10, 'active' => true]);

        $this->actingAs($admin)
            ->postJson('/admin/workshop-technicians', [
                'name' => 'فني تجريبي',
                'username' => 'tech_test_01',
                'password' => 'secret123',
                'status' => User::STATUS_ACTIVE,
                'section_ids' => [$section->id],
            ])
            ->assertCreated()
            ->assertJsonFragment(['message' => 'تم إضافة الفني.']);

        $tech = User::query()->where('username', 'tech_test_01')->first();
        $this->assertNotNull($tech);
        $this->assertTrue($tech->workshopSections()->whereKey($section->id)->exists());

        $this->actingAs($admin)
            ->putJson("/admin/workshop-technicians/{$tech->id}", [
                'name' => 'فني معدّل',
                'section_ids' => [],
            ])
            ->assertOk()
            ->assertJsonFragment(['message' => 'تم تحديث بيانات الفني.']);

        $tech->refresh();
        $this->assertSame('فني معدّل', $tech->name);
        $this->assertFalse($tech->workshopSections()->exists());

        $this->actingAs($admin)
            ->deleteJson("/admin/workshop-technicians/{$tech->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'تم حذف الفني.']);

        $this->assertDatabaseMissing('users', ['id' => $tech->id]);
    }

    public function test_workshop_technician_delete_blocked_when_assigned_to_case(): void
    {
        $admin = $this->userWithRole(Role::SLUG_ADMIN);
        $tech = $this->userWithRole(Role::SLUG_WORKSHOP);
        $patient = $this->militaryPatient($this->militaryCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING);
        $case->update(['assigned_technician_id' => $tech->id]);

        $this->actingAs($admin)
            ->deleteJson("/admin/workshop-technicians/{$tech->id}")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'لا يمكن حذف الفني — مرتبط بحالات إنتاج.']);
    }

    public function test_operations_approve_issues_work_order_without_production_assignment(): void
    {
        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        $patient = $this->militaryPatient($this->militaryCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);
        $case->update(['work_order_no' => null, 'manufacturing_stage' => null]);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'name' => 'صنف RM-001', 'qty' => 1],
        ]);
        app(WorkOrderService::class)->generate($case->fresh());

        $ops = $this->userWithRole(Role::SLUG_OPERATIONS);

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/approve")
            ->assertOk();

        $case->refresh();
        $this->assertNull($case->workshop_section_id);
        $this->assertNull($case->assigned_technician_id);
        $this->assertNull($case->workshop_assignment_approved_at);
        $this->assertNotNull($case->work_order_no);
        $this->assertSame(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
    }

    public function test_production_assignment_approval_allows_dispense(): void
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

        $case = $this->seedWorkshopAssignmentApproved($case->fresh());

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

    public function test_dispense_blocked_without_production_assignment_approval(): void
    {
        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        $patient = $this->militaryPatient($this->militaryCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0100']);

        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'name' => 'صنف RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->reserveForCase($case->fresh());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);
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

        $case = $this->seedWorkshopAssignmentApproved($case->fresh());

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
