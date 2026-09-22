<?php

namespace Tests\Feature\Inventory;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\ReturnNoteService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class FractionalDispenseReturnTest extends TestCase
{
    use ProstheticTestHelper;

    private function meterItemWithStock(): array
    {
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-FRAC-M', qty: 10, wac: 100);
        $item->update(['uom' => 'متر']);
        app(StockPriceService::class)->addBatch($item->fresh(), 10, 100.00, $supplier, 'INV-FRAC', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('technical');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-FRAC-01']);

        return compact('item', 'case', 'user');
    }

    public function test_dispense_nine_point_two_mm_from_ten_meters_balance(): void
    {
        ['item' => $item, 'case' => $case, 'user' => $user] = $this->meterItemWithStock();
        $this->actingAs($user);

        $bomService = app(BomService::class);
        $bom = $bomService->create($case, [[
            'stock_item_code' => 'RM-FRAC-M',
            'qty' => '0.92 سم',
        ]]);

        $this->assertEqualsWithDelta(0.0092, (float) $bom->items->first()->qty, 0.00001);

        $bomService->releaseToWip($bom, [[
            'barcode' => $item->barcode,
            'qty' => '0.92 سم',
        ]]);

        $item->refresh();
        $this->assertEqualsWithDelta(9.9908, (float) $item->qty, 0.0001);
    }

    public function test_return_fractional_meter_restores_stock_balance(): void
    {
        ['item' => $item, 'case' => $case, 'user' => $user] = $this->meterItemWithStock();
        $this->actingAs($user);

        $bomService = app(BomService::class);
        $bom = $bomService->create($case, [[
            'stock_item_code' => 'RM-FRAC-M',
            'qty' => 0.0092,
        ]]);

        $bomService->releaseToWip($bom, [['barcode' => $item->barcode, 'qty' => 0.0092]]);
        $item->refresh();
        $this->assertEqualsWithDelta(9.9908, (float) $item->qty, 0.0001);

        $bom->update(['stage' => Bom::STAGE_FINISHED]);
        $case->update(['stage_key' => CaseRecord::STAGE_DELIVERED]);

        $note = app(ReturnNoteService::class)->create($bom->fresh(), [[
            'stock_item_code' => 'RM-FRAC-M',
            'qty' => '0.92 سم',
        ]], 'اختبار كسر', $user);

        app(ReturnNoteService::class)->complete($note, [[
            'line_id' => $note->lines->first()->id,
            'barcode' => $item->barcode,
            'qty_returned' => '0.92 سم',
        ]]);

        $item->refresh();
        $this->assertEqualsWithDelta(10.0, (float) $item->qty, 0.0001);
    }
}
