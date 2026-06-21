<?php

namespace Tests\Feature\Inventory;

use App\Models\Bom;
use App\Models\ReturnNote;
use App\Services\BomService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ReturnNoteListTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_returns_create_endpoint_lists_wip_boms_with_returnable_items(): void
    {
        $this->seedStock();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, \App\Models\CaseRecord::STAGE_MANUFACTURING, \App\Models\CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0500']);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);
        $bom->items()->update(['unit_cost' => 200]);
        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-001']);

        $this->getJson('/technical/returns/create')
            ->assertOk()
            ->assertJsonPath('boms.0.bom_no', $bom->fresh()->bom_no)
            ->assertJsonPath('boms.0.items.0.returnable_qty', 2);
    }

    public function test_returns_list_shows_existing_return_notes(): void
    {
        $this->seedStock();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, \App\Models\CaseRecord::STAGE_MANUFACTURING, \App\Models\CaseRecord::MFG_WAREHOUSE);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        $bom->items()->update(['unit_cost' => 200]);
        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-001']);

        app(\App\Services\ReturnNoteService::class)->create(
            $bom->fresh(),
            [['stock_item_code' => 'RM-001', 'qty' => 1, 'name' => 'صنف RM-001']],
            'فائض عن الحاجة',
            $user,
        );

        $this->getJson('/technical/returns/list')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    private function seedStock(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
