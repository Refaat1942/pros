<?php

namespace Tests\Feature\Inventory;

use App\Models\Bom;
use App\Models\ReturnNote;
use App\Services\BomService;
use App\Services\ReturnNoteService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ReturnNoteListTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_workshop_returns_create_lists_wip_boms_with_returnable_items(): void
    {
        $this->seedStock();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('workshop');
        $case    = $this->caseAtStage($patient, \App\Models\CaseRecord::STAGE_MANUFACTURING, \App\Models\CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0500']);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);
        $bom->items()->update(['unit_cost' => 200]);
        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-001']);

        $this->getJson('/workshop/returns/create')
            ->assertOk()
            ->assertJsonPath('boms.0.bom_no', $bom->fresh()->bom_no)
            ->assertJsonPath('boms.0.items.0.returnable_qty', 2);
    }

    public function test_workshop_returns_list_shows_existing_return_notes(): void
    {
        $this->seedStock();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('workshop');
        $case    = $this->caseAtStage($patient, \App\Models\CaseRecord::STAGE_MANUFACTURING, \App\Models\CaseRecord::MFG_WAREHOUSE);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        $bom->items()->update(['unit_cost' => 200]);
        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-001']);

        app(ReturnNoteService::class)->create(
            $bom->fresh(),
            [['stock_item_code' => 'RM-001', 'qty' => 1, 'name' => 'صنف RM-001']],
            'فائض عن الحاجة',
            $user,
        );

        $this->getJson('/workshop/returns/list')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_warehouse_returns_inbox_excludes_completed_notes(): void
    {
        $this->seedStock();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $ops     = $this->userWithRole('workshop');
        $tech    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, \App\Models\CaseRecord::STAGE_MANUFACTURING, \App\Models\CaseRecord::MFG_WAREHOUSE);

        $this->actingAs($ops);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);
        $bom->items()->update(['unit_cost' => 200]);
        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-001']);

        $pending = app(ReturnNoteService::class)->create(
            $bom->fresh(),
            [['stock_item_code' => 'RM-001', 'qty' => 1, 'name' => 'صنف RM-001']],
            'فائض',
            $ops,
        );

        $completedBom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        $completedBom->items()->update(['unit_cost' => 200]);
        app(BomService::class)->releaseToWip($completedBom->fresh(), ['BC-RM-001']);

        $done = app(ReturnNoteService::class)->create(
            $completedBom->fresh(),
            [['stock_item_code' => 'RM-001', 'qty' => 1, 'name' => 'صنف RM-001']],
            'مكتمل',
            $ops,
        );
        $done->update(['status' => ReturnNote::STATUS_COMPLETED, 'completed_at' => now()]);

        $this->actingAs($tech);

        $this->getJson('/technical/returns/list?inbox=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $pending->id);
    }

    private function seedStock(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
