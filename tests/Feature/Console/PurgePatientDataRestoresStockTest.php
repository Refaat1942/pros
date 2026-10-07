<?php

namespace Tests\Feature\Console;

use App\Models\CaseRecord;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Services\BomService;
use App\Services\PatientDataPurgeService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestCase;

/**
 * مسح بيانات المرضى يعيد ما صُرف للحالات إلى المخزن — حتى للصنف المرفوع من الشيت بلا حركات.
 */
class PurgePatientDataRestoresStockTest extends ProstheticTestCase
{
    public function test_purge_returns_dispensed_qty_to_item_and_price_batches(): void
    {
        // صنف من الشيت: رصيد عشري ولا توجد له أي حركة مخزن.
        $item = $this->stockItem('RM-001', qty: 20);
        $item->update(['qty' => 12.5, 'uom' => 'متر', 'price' => 40]);

        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item->fresh(), 2.5, 50.00, $supplier, 'INV-PURGE', now());
        $item->update(['qty' => 15.0]);

        $case = $this->caseAtStage($this->civilianPatient($this->civilianCompany()), CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0099']);
        $this->actingAs($this->userWithRole('technical'));

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 13.25]]);
        $this->releaseBomToWip($bom, [['barcode' => $item->barcode, 'qty' => 13.25]]);

        $this->assertEqualsWithDelta(1.75, (float) $item->fresh()->qty, 0.0001);

        app(PatientDataPurgeService::class)->purge();

        $this->assertEqualsWithDelta(15.0, (float) $item->fresh()->qty, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $item->fresh()->reserved, 0.0001);

        $batches = StockItemPrice::query()->where('stock_item_id', $item->id)->get();
        $this->assertEqualsWithDelta(2.5, (float) $batches->firstWhere('price_ref', '!=', StockItemPrice::openingRefFor($item))?->qty, 0.0001);
        $this->assertEqualsWithDelta(15.0, (float) $batches->sum('qty'), 0.0001);
        $this->assertSame(0, StockItem::query()->where('qty', '<', 0)->count());
    }
}
