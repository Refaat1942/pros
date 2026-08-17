<?php

namespace Tests\Feature\Pipeline;

use App\Models\Role;
use App\Models\WorkshopSection;
use App\Services\BomService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class WorkshopTechnicianBoardTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_technician_board_groups_assigned_orders(): void
    {
        $this->seedStockWithPriceBatch();

        $patient = $this->civilianPatient($this->civilianCompany());
        $user = $this->userWithRole('workshop');
        $tech = $this->userWithRole(Role::SLUG_WORKSHOP);
        $tech->update(['name' => 'فني تجريبي']);

        $section = WorkshopSection::create(['name' => 'تجميع', 'code' => 'assembly', 'sort' => 10, 'active' => true]);
        $section->technicians()->sync([$tech->id]);

        $case = $this->caseAtStage($patient, \App\Models\CaseRecord::STAGE_MANUFACTURING, \App\Models\CaseRecord::MFG_WAREHOUSE);
        $case->update([
            'work_order_no' => 'WO-2026-0200',
            'workshop_section_id' => $section->id,
            'assigned_technician_id' => $tech->id,
            'workshop_progress_pct' => 40,
        ]);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $this->getJson('/workshop/technicians/board')
            ->assertOk()
            ->assertJsonPath('summary.total_wip', 1)
            ->assertJsonPath('summary.assigned', 1)
            ->assertJsonPath('summary.unassigned', 0)
            ->assertJsonPath('technicians.0.technician.name', 'فني تجريبي')
            ->assertJsonPath('technicians.0.orders.0.work_order_no', 'WO-2026-0200')
            ->assertJsonPath('technicians.0.orders.0.progress_pct', 40);
    }

    public function test_workshop_progress_update_persists(): void
    {
        $this->seedStockWithPriceBatch();

        $patient = $this->civilianPatient($this->civilianCompany());
        $user = $this->userWithRole('workshop');
        $case = $this->caseAtStage($patient, \App\Models\CaseRecord::STAGE_MANUFACTURING, \App\Models\CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0201']);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $this->postJson("/workshop/workshop/{$case->id}/progress", ['progress_pct' => 75])
            ->assertOk()
            ->assertJsonPath('case.workshop_progress_pct', 75);

        $this->assertSame(75, $case->fresh()->workshop_progress_pct);
    }

    public function test_workshop_page_shows_technician_tracking_panel(): void
    {
        $user = $this->userWithRole('workshop');

        $this->actingAs($user)
            ->get('/workshop/workshop')
            ->assertOk()
            ->assertSee('id="workshopTechnicianBoard"', false)
            ->assertSee('تتبع الفنيين', false);
    }

    private function seedStockWithPriceBatch(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
