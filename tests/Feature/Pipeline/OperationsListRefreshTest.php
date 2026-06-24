<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class OperationsListRefreshTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_operations_page_includes_refresh_button(): void
    {
        $user = $this->userWithRole('operations');

        $this->actingAs($user)
            ->get('/operations/operations')
            ->assertOk()
            ->assertSee('id="btnRefreshOps"', false)
            ->assertSee('id="opsTableBody"', false);
    }

    public function test_operations_list_endpoint_returns_active_manufacturing_cases(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('operations');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0100']);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $response = $this->getJson('/operations/operations/list');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('data.0.work_order_no', 'WO-2026-0100');
        $response->assertJsonPath('data.0.patient.name', $patient->name);
    }

    public function test_operations_list_endpoint_returns_bom_items_for_view_modal(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('operations');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0100']);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'name' => 'خام اختبار', 'qty' => 2],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $this->getJson('/operations/operations/list')
            ->assertOk()
            ->assertJsonPath('data.0.bom.items.0.name', 'خام اختبار')
            ->assertJsonPath('data.0.bom.items.0.qty', 2);
    }

    private function seedStockWithPriceBatch(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
