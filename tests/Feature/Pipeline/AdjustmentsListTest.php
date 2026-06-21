<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\FittingTrial;
use App\Services\BomService;
use App\Services\FittingTrialService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdjustmentsListTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_adjustments_page_includes_dynamic_table_controls(): void
    {
        $user = $this->userWithRole('adjustments');

        $this->actingAs($user)
            ->get('/adjustments/adjustments')
            ->assertOk()
            ->assertSee('id="btnRefreshAdj"', false)
            ->assertSee('id="adjustmentsTable"', false);
    }

    public function test_adjustments_list_excludes_manufacturing_before_bom_release(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('adjustments');

        $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);

        $this->actingAs($user)
            ->getJson('/adjustments/adjustments/list')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_civilian_case_appears_after_bom_released_to_workshop(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('adjustments');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0200']);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $this->getJson('/adjustments/adjustments/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.work_order_no', 'WO-2026-0200')
            ->assertJsonPath('data.0.pathway_label', 'مدني')
            ->assertJsonPath('data.0.patient.name', $patient->name);
    }

    public function test_military_case_appears_after_bom_released_to_workshop(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $user    = $this->userWithRole('adjustments');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0300']);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $this->getJson('/adjustments/adjustments/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.pathway_label', 'عسكري');
    }

    public function test_ready_delivery_case_appears_in_adjustments_list(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('adjustments');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0400']);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);
        $case = $this->advanceCaseToFinishing($case->fresh());
        app(BomService::class)->finish($case->bom);

        $this->getJson('/adjustments/adjustments/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.stage_key', CaseRecord::STAGE_READY_DELIVERY);
    }

    public function test_fitting_trial_service_persists_for_eligible_case(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('adjustments');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);

        $this->actingAs($user);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        app(FittingTrialService::class)->save($case->fresh()->load('bom'), [
            'trial1_date' => now()->toDateString(),
            'notes'       => 'تعديل بطانة الساق',
        ]);

        $this->assertDatabaseHas('fitting_trials', [
            'case_id' => $case->id,
            'status'  => FittingTrial::STATUS_TRIAL1,
            'notes'   => 'تعديل بطانة الساق',
        ]);
    }

    private function seedStockWithPriceBatch(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
