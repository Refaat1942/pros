<?php

namespace Tests\Feature\Reports;

use App\Models\ReturnNote;
use App\Services\BomService;
use App\Services\ReturnNoteService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminReturnsPageTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_returns_page_lists_return_notes_and_items(): void
    {
        $this->seedStock();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $tech    = $this->userWithRole('technical');
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, \App\Models\CaseRecord::STAGE_MANUFACTURING, \App\Models\CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0900']);

        $this->actingAs($tech);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);
        $bom->items()->update(['unit_cost' => 200]);
        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-001']);

        $note = app(ReturnNoteService::class)->create(
            $bom->fresh(),
            [['stock_item_code' => 'RM-001', 'qty' => 2, 'name' => 'صنف RM-001']],
            'فائض عن الحاجة',
            $tech,
        );

        app(ReturnNoteService::class)->complete($note, [
            ['line_id' => $note->lines->first()->id, 'barcode' => 'BC-RM-001', 'qty_returned' => 2],
        ]);

        $this->actingAs($admin)
            ->get('/admin/returns')
            ->assertOk()
            ->assertSee('سجل إذونات الارتجاع')
            ->assertSee($note->fresh()->return_no)
            ->assertSee('RM-001')
            ->assertSee('الأصناف المرتجعة');
    }

    private function seedStock(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
