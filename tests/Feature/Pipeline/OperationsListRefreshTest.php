<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Services\BomService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class OperationsListRefreshTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_operations_delivery_page_includes_refresh_button(): void
    {
        $user = $this->userWithRole('operations');

        $this->actingAs($user)
            ->get('/operations/operations')
            ->assertOk()
            ->assertSee('id="btnRefreshOps"', false)
            ->assertSee('id="opsTableBody"', false)
            ->assertSee('تم التسليم', false);
    }

    public function test_operations_list_returns_only_ready_delivery_cases(): void
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

        $this->getJson('/operations/operations/list')
            ->assertOk()
            ->assertJsonPath('total', 0);

        app(BomService::class)->finish($bom->fresh());

        $this->getJson('/operations/operations/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.work_order_no', 'WO-2026-0100')
            ->assertJsonPath('data.0.patient.name', $patient->name)
            ->assertJsonPath('summary.ready', 1);
    }

    public function test_operations_list_shows_armed_forces_as_company_for_military_cases(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->militaryPatient($company);
        $user    = $this->userWithRole('operations');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_READY_DELIVERY);
        $case->update([
            'path'          => CaseRecord::PATH_MILITARY,
            'work_order_no' => 'WO-2026-0002',
            'company_name'  => null,
        ]);

        Bom::create([
            'bom_no'       => 'BOM-MIL-01',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'stage'        => Bom::STAGE_FINISHED,
            'finished_at'  => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/operations/operations/list')
            ->assertOk()
            ->assertJsonPath('data.0.company_name', Patient::MILITARY_SOVEREIGN_ENTITY)
            ->assertJsonPath('data.0.pathway_label', 'عسكري');
    }

    public function test_operations_deliver_closes_case_from_desk(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('operations');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_READY_DELIVERY);
        $case->update(['work_order_no' => 'WO-2026-0101', 'quote_total' => 400.00]);

        Bom::create([
            'bom_no'       => 'BOM-DEL-01',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'stage'        => Bom::STAGE_FINISHED,
            'finished_at'  => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/operations/operations/{$case->id}/deliver")
            ->assertOk()
            ->assertJsonPath('closed', true);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_DELIVERED, $case->stage_key);

        $this->getJson('/operations/operations/list')
            ->assertOk()
            ->assertJsonPath('summary.done', 1)
            ->assertJsonPath('total', 0);
    }

    private function seedStockWithPriceBatch(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
