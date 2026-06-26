<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class WorkshopListRefreshTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_workshop_page_includes_refresh_button(): void
    {
        $user = $this->userWithRole('workshop');

        $this->actingAs($user)
            ->get('/workshop/workshop')
            ->assertOk()
            ->assertSee('id="btnRefreshWorkshop"', false)
            ->assertSee('id="workshopTableBody"', false)
            ->assertSee('تم التصنيع', false);
    }

    public function test_workshop_list_endpoint_returns_wip_cases(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('workshop');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0100']);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $this->getJson('/workshop/workshop/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.work_order_no', 'WO-2026-0100')
            ->assertJsonPath('data.0.patient.name', $patient->name)
            ->assertJsonPath('summary.wip', 1);
    }

    public function test_workshop_list_exposes_work_order_print_url(): void
    {
        $this->seedStockWithPriceBatch();
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->dispensedManufacturingCase($patient);
        $user = $this->userWithRole('workshop');

        $this->actingAs($user)
            ->getJson('/workshop/workshop/list')
            ->assertOk()
            ->assertJsonPath('data.0.work_order_print_url', route('workshop.work-order.print', $case));
    }

    public function test_workshop_finish_removes_case_from_list(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('workshop');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0100']);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $this->postJson("/workshop/workshop/{$case->id}/finish-quality")->assertOk();

        $this->getJson('/workshop/workshop/list')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('summary.wip', 0);
    }

    private function seedStockWithPriceBatch(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
