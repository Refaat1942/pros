<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\Dashboard\DashboardPageDataService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * BOM صرف → WIP يجب أن يُظهر الحالة فوراً في مكتب التشغيل مع WO.
 */
class OperationsDispenseVisibilityTest extends TestCase
{
    use ProstheticTestHelper;

    private function prepareStock(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }

    public function test_civilian_spec_raw_dispense_promotes_to_operations_with_work_order(): void
    {
        $this->prepareStock();
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_WAITING_RETURN);

        $this->actingAs($user);

        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $case->refresh();
        $bom->refresh();

        $this->assertEquals(Bom::STAGE_WIP, $bom->stage);
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertEquals(CaseRecord::MFG_ISSUE, $case->manufacturing_stage);
        $this->assertNotEmpty($case->work_order_no);
        $this->assertMatchesRegularExpression('/^WO-\d{4}-\d{4}$/', $case->work_order_no);
    }

    public function test_orphan_wip_case_repaired_on_operations_desk_load(): void
    {
        $this->prepareStock();
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_WAITING_RETURN);

        $this->actingAs($user);

        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        // محاكاة البيانات القديمة: BOM=WIP لكن الحالة لم تُنقل للتصنيع
        $bom->update(['stage' => Bom::STAGE_WIP, 'released_at' => now()]);

        app(BomService::class)->repairOrphanWipCases();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertNotEmpty($case->work_order_no);

        $data = app(DashboardPageDataService::class)->resolve('operations', 'operations');
        $ids  = collect($data['ops_cases'])->pluck('id');

        $this->assertTrue($ids->contains($case->id), 'الحالة يجب أن تظهر في مكتب التشغيل');
    }

    public function test_manufacturing_warehouse_dispense_advances_to_issue(): void
    {
        $this->prepareStock();
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $user    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0099']);

        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $case->refresh();
        $this->assertEquals(CaseRecord::MFG_ISSUE, $case->manufacturing_stage);
        $this->assertEquals('WO-2026-0099', $case->work_order_no);
    }

    public function test_dispense_blocked_when_case_not_ready_for_workshop(): void
    {
        $this->prepareStock();
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);

        $this->actingAs($user);

        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);
    }

    public function test_operations_desk_hidden_until_warehouse_dispense(): void
    {
        $this->prepareStock();
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $ops     = $this->userWithRole('operations');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0200']);

        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        $data = app(DashboardPageDataService::class)->resolve('operations', 'operations');
        $ids  = collect($data['ops_cases'])->pluck('id');

        $this->assertFalse(
            $ids->contains($case->id),
            'الحالة لا يجب أن تظهر في الورشة قبل صرف المواد من المخزن'
        );

        $this->actingAs($ops);
        $this->postJson("/operations/operations/{$case->id}/advance", [
            'manufacturing_stage' => CaseRecord::MFG_ISSUE,
        ])->assertStatus(422);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $data = app(DashboardPageDataService::class)->resolve('operations', 'operations');
        $ids  = collect($data['ops_cases'])->pluck('id');

        $this->assertTrue(
            $ids->contains($case->id),
            'الحالة يجب أن تظهر في الورشة بعد صرف المواد من المخزن'
        );
    }
}
