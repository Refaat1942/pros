<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * تسليم المرضى صار حصرياً في الاستقبال (أُزيل تسليم المخزن نهائياً).
 */
class OperationsListRefreshTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_reception_delivery_page_includes_scan_ui(): void
    {
        $user = $this->userWithRole('reception');

        $this->actingAs($user)
            ->get('/reception/delivery')
            ->assertOk()
            ->assertSee('id="deliveryList"', false)
            ->assertSee('id="deliveryQrInput"', false)
            ->assertSee('id="btnConfirmDelivery"', false);
    }

    public function test_reception_delivery_list_returns_only_ready_delivery_cases(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('reception');
        $tech = $this->userWithRole('technical');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0100']);

        $this->actingAs($tech);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $this->actingAs($user)
            ->getJson('/reception/delivery/list')
            ->assertOk()
            ->assertJsonPath('total', 0);

        app(BomService::class)->finish($bom->fresh());

        $this->actingAs($user)
            ->getJson('/reception/delivery/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.work_order_no', 'WO-2026-0100')
            ->assertJsonPath('data.0.patient.name', $patient->name);
    }

    public function test_reception_delivery_scan_closes_ready_case(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('reception');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_READY_DELIVERY);
        $case->update(['work_order_no' => 'WO-2026-0101', 'quote_total' => 400.00]);

        Bom::create([
            'bom_no' => 'BOM-DEL-01',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'stage' => Bom::STAGE_FINISHED,
            'finished_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/reception/delivery/scan', ['scanned_qr' => $patient->patient_qr])
            ->assertOk()
            ->assertJsonPath('closed', true);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_DELIVERED, $case->stage_key);

        $this->actingAs($user)
            ->getJson('/reception/delivery/list')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_legacy_operations_delivery_url_redirects_to_reception(): void
    {
        $user = $this->userWithRole('operations');

        $this->actingAs($user)
            ->get('/operations/operations')
            ->assertRedirect('/reception/delivery');
    }

    private function seedStockWithPriceBatch(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
