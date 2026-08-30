<?php

namespace Tests\Feature\Stock;

use App\Models\StockItemPrice;
use App\Services\StockCatalogService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestCase;

class CatalogUpdateWarehouseBatchTest extends ProstheticTestCase
{
    public function test_update_preserves_warehouse_batches_when_syncing_catalog_extra_prices(): void
    {
        $admin = $this->userWithRole('admin');
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-CAT-SAVE', qty: 15);

        app(StockPriceService::class)->addBatch($item->fresh(), 10, 100.00, $supplier, 'INV-WH', now());
        app(StockPriceService::class)->addBatch($item->fresh(), 5, 200.00, $supplier, 'INV-WH-2', now());

        $warehouseBatchCount = StockItemPrice::query()->where('stock_item_id', $item->id)->count();
        $this->assertSame(2, $warehouseBatchCount);

        $updated = app(StockCatalogService::class)->update($item->fresh(), [
            'name' => 'صنف محدّث',
            'price' => 100.00,
            'opening_qty' => 0,
            'addition' => 15,
            'discount' => 0,
            'balance' => 15,
            'prices' => [
                ['amount' => 3000.00],
            ],
            'supplier_ids' => [$supplier->id],
        ]);

        $this->assertSame('صنف محدّث', $updated->name);

        $batches = StockItemPrice::query()->where('stock_item_id', $item->id)->get();
        $this->assertGreaterThanOrEqual(3, $batches->count());
        $this->assertTrue($batches->contains(fn (StockItemPrice $b) => (float) $b->amount === 100.0 && (float) $b->qty === 10.0));
        $this->assertTrue($batches->contains(fn (StockItemPrice $b) => (float) $b->amount === 200.0 && (float) $b->qty === 5.0));
        $this->assertTrue($batches->contains(fn (StockItemPrice $b) => (float) $b->amount === 3000.0 && (float) $b->qty === 0.0));
    }

    public function test_catalog_extra_prices_excludes_warehouse_batches(): void
    {
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-CAT-EXTRA', qty: 8);

        app(StockPriceService::class)->addBatch($item->fresh(), 8, 150.00, $supplier, 'INV-ONLY', now());

        $formatted = app(StockCatalogService::class)->formatItem($item->fresh(['prices', 'suppliers', 'category']));

        $this->assertEmpty($formatted['catalog_extra_prices']);
        $this->assertCount(1, $formatted['price_tiers']);
        $this->assertEqualsWithDelta(150.0, (float) $formatted['price_tiers'][0]['amount'], 0.01);
        $this->assertEqualsWithDelta(8.0, (float) $formatted['price_tiers'][0]['qty'], 0.01);
    }
}
