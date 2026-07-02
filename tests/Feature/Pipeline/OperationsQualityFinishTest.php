<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class OperationsQualityFinishTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_finish_manufacturing_requires_wip_bom(): void
    {
        $item = $this->stockItem('RM-001', qty: 10);
        app(StockPriceService::class)->addBatch($item, 10, 200.0, $this->makeSupplier(), 'INV-A', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('workshop');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-QC-01']);
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 1]]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BomService::class)->finish($bom->fresh());
    }

    public function test_finish_manufacturing_via_endpoint_closes_bom_and_ready_delivery(): void
    {
        $item = $this->stockItem('RM-001', qty: 10);
        app(StockPriceService::class)->addBatch($item, 10, 200.0, $this->makeSupplier(), 'INV-A', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('workshop');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-QC-02']);
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 1]]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $response = $this->postJson("/workshop/workshop/{$case->id}/finish-quality");

        $response->assertOk()
            ->assertJsonPath('message', 'تم التصنيع — يُرجى توجيه العميل إلى المخزن للتسليم.')
            ->assertJsonPath('bom.stage', Bom::STAGE_FINISHED);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_READY_DELIVERY, $case->stage_key);
        $this->assertEquals(CaseRecord::MFG_CLOSED, $case->manufacturing_stage);
    }
}
