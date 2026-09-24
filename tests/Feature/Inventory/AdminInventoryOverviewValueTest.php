<?php

namespace Tests\Feature\Inventory;

use App\Models\StockItemPrice;
use App\Services\CostingModeService;
use App\Services\Dashboard\DashboardPageDataService;
use App\Services\InventoryValuationService;
use App\Services\PriceBatchDispenseService;
use App\Services\StockCatalogService;
use App\Services\StockPriceService;
use App\Support\CostingEngine;
use Carbon\Carbon;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminInventoryOverviewValueTest extends TestCase
{
    use ProstheticTestHelper;

    private function expectedSelling(float $limbMaterials, float $quickMaterials): float
    {
        $modes = app(CostingModeService::class);

        return (float) app(CostingEngine::class)->calculateSplit(
            $modes->limbProfile(),
            $modes->quickProfile(),
            $limbMaterials,
            $quickMaterials,
        )['selling_price'];
    }

    public function test_sheet_item_valued_at_base_price_not_stale_wac(): void
    {
        $item = $this->stockItem('RM-SHEET', qty: 10, wac: 1750);
        $item->update(['price' => 1000]);

        $summary = app(InventoryValuationService::class)->summary();

        $this->assertEqualsWithDelta(10000.0, $summary['cost_value'], 0.01);
        $this->assertEqualsWithDelta(10000.0, $summary['opening_cost_value'], 0.01);
        $this->assertEqualsWithDelta($this->expectedSelling(10000, 0), $summary['selling_value'], 0.01);
        $this->assertGreaterThan($summary['cost_value'], $summary['selling_value']);
    }

    public function test_inventory_overview_cards_show_cost_and_selling_in_whole_numbers(): void
    {
        $item = $this->stockItem('RM-099', qty: 10, wac: 0);
        $item->update(['price' => 250]);

        $data = app(DashboardPageDataService::class)->resolve('admin', 'inventory-overview');
        $stats = collect($data['inventory_overview_stats']);

        $this->assertSame('2,500', $stats->firstWhere('label', 'قيمة المخزون — التكلفة (FIFO)')['value'] ?? null);
        $this->assertSame(
            number_format(round($this->expectedSelling(2500, 0))),
            $stats->firstWhere('label', 'قيمة المخزون — سعر البيع')['value'] ?? null,
        );
    }

    public function test_quick_dispense_items_use_quick_profit_for_selling_value(): void
    {
        $item = $this->stockItem('RM-QK', qty: 2, wac: 100, quick: true);
        $item->update(['price' => 100]);

        $summary = app(InventoryValuationService::class)->summary();

        $this->assertEqualsWithDelta(200.0, $summary['cost_value'], 0.01);
        $this->assertEqualsWithDelta($this->expectedSelling(0, 200), $summary['selling_value'], 0.01);
    }

    public function test_fractional_qty_counts_and_backorders_are_zero(): void
    {
        $meters = $this->stockItem('RM-M01', qty: 10, wac: 100);
        $meters->update(['qty' => 2.5]);

        $backorder = $this->stockItem('RM-B01', qty: 1, wac: 400);
        $backorder->update(['qty' => -3]);

        $summary = app(InventoryValuationService::class)->summary();

        $this->assertEqualsWithDelta(250.0, $summary['cost_value'], 0.01);
        $this->assertSame(1, $summary['stocked_items']);
    }

    public function test_remaining_stock_valued_at_fifo_layers_after_dispense(): void
    {
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-FIFO-VAL', qty: 15, wac: 100);
        $prices = app(StockPriceService::class);
        $prices->addBatch($item->fresh(), 10, 100.00, $supplier, 'INV-A', Carbon::parse('2026-01-01'));
        $prices->addBatch($item->fresh(), 5, 200.00, $supplier, 'INV-B', Carbon::parse('2026-02-01'));

        $dispense = app(PriceBatchDispenseService::class);
        $allocations = $dispense->allocateForDispense($item->fresh(), 3);
        $dispense->applyDecrements($allocations);
        $item->decrement('qty', 3);

        $row = app(InventoryValuationService::class)->valueItem($item->fresh());

        $this->assertEqualsWithDelta(7 * 100 + 5 * 200, $row['cost_value'], 0.01);
        $this->assertEqualsWithDelta(0.0, $row['opening_qty'], 0.0001);
    }

    public function test_dispense_of_sheet_stock_uses_opening_layer_at_base_price(): void
    {
        $item = $this->stockItem('RM-OPEN-DISP', qty: 10, wac: 1750);
        $item->update(['price' => 1000]);

        $dispense = app(PriceBatchDispenseService::class);
        $allocations = $dispense->allocateForDispense($item->fresh(), 4);

        $this->assertCount(1, $allocations);
        $this->assertEqualsWithDelta(1000.0, $allocations[0]['unit_price'], 0.01);

        $opening = StockItemPrice::find($allocations[0]['batch_id']);
        $this->assertTrue($opening->isOpeningLayer());
        $this->assertEqualsWithDelta(10.0, (float) $opening->qty, 0.0001);

        $dispense->applyDecrements($allocations);
        $item->decrement('qty', 4);

        $row = app(InventoryValuationService::class)->valueItem($item->fresh());
        $this->assertEqualsWithDelta(6000.0, $row['cost_value'], 0.01);
    }

    public function test_opening_layer_consumed_before_later_receipts(): void
    {
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-OPEN-FIRST', qty: 10, wac: 50);
        $item->update(['price' => 50]);

        app(StockPriceService::class)->addBatch($item->fresh(), 5, 300.00, $supplier, 'INV-LATER', Carbon::parse('2026-03-01'));
        $item->increment('qty', 5);

        $allocations = app(PriceBatchDispenseService::class)->allocateForDispense($item->fresh(), 12);

        $this->assertEqualsWithDelta(50.0, $allocations[0]['unit_price'], 0.01);
        $this->assertEqualsWithDelta(10.0, $allocations[0]['qty'], 0.0001);
        $this->assertEqualsWithDelta(300.0, $allocations[1]['unit_price'], 0.01);
        $this->assertEqualsWithDelta(2.0, $allocations[1]['qty'], 0.0001);
    }

    public function test_catalog_update_refreshes_stale_wac(): void
    {
        $item = $this->stockItem('RM-STALE', qty: 10, wac: 1750);
        $item->update(['price' => 1000]);

        app(StockCatalogService::class)->update($item->fresh(), [
            'name' => $item->name,
            'price' => 1000,
            'opening_qty' => 10,
            'addition' => 0,
            'discount' => 0,
            'balance' => 10,
        ]);

        $this->assertEqualsWithDelta(1000.0, (float) $item->fresh()->wac, 0.0001);
    }
}
