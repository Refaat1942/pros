<?php

namespace Tests\Feature\Inventory;

use App\Models\StockItemPrice;
use App\Services\Dashboard\DashboardPageDataService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminInventoryOverviewValueTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_inventory_overview_value_uses_wac_or_highest_batch_price(): void
    {
        $item = $this->stockItem('RM-099', qty: 10, wac: 0);
        $item->update(['price' => 0]);

        $supplier = $this->makeSupplier();
        StockItemPrice::create([
            'stock_item_id' => $item->id,
            'price_ref' => 'PR-RM-099-1',
            'supplier_id' => $supplier->id,
            'amount' => 250.00,
            'qty' => 10,
        ]);

        $data = app(DashboardPageDataService::class)->resolve('admin', 'inventory-overview');
        $stats = collect($data['inventory_overview_stats']);
        $valueStat = $stats->firstWhere('label', 'قيمة المخزون');

        $this->assertNotNull($valueStat);
        $this->assertSame('2,500', $valueStat['value']);
    }

    public function test_inventory_value_counts_fractional_qty_and_ignores_backorders(): void
    {
        $meters = $this->stockItem('RM-M01', qty: 10, wac: 100);
        $meters->update(['qty' => 2.5]);

        $backorder = $this->stockItem('RM-B01', qty: 1, wac: 400);
        $backorder->update(['qty' => -3]);

        $valuation = app(StockPriceService::class)->inventoryValuation();

        $this->assertEqualsWithDelta(250.0, $valuation['wac_value'], 0.01);
    }
}
