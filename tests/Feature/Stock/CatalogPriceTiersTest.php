<?php

namespace Tests\Feature\Stock;

use App\Services\StockCatalogService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestCase;

class CatalogPriceTiersTest extends ProstheticTestCase
{
    public function test_format_item_includes_aggregated_price_tier_quantities(): void
    {
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-TIER-QTY', qty: 25);
        $priceService = app(StockPriceService::class);

        $priceService->addBatch($item->fresh(), 5, 10.00, $supplier, 'INV-A', now());
        $priceService->addBatch($item->fresh(), 7, 20.00, $supplier, 'INV-B', now());
        $priceService->addBatch($item->fresh(), 3, 10.00, $supplier, 'INV-C', now());

        $item->update(['price' => 10.00]);
        $formatted = app(StockCatalogService::class)->formatItem($item->fresh(['prices']));

        $this->assertArrayHasKey('price_tiers', $formatted);
        $tiers = collect($formatted['price_tiers']);

        $tier10 = $tiers->firstWhere('amount', 10.0);
        $tier20 = $tiers->firstWhere('amount', 20.0);

        $this->assertNotNull($tier10);
        $this->assertEqualsWithDelta(8.0, (float) $tier10['qty'], 0.0001);
        $this->assertNotNull($tier20);
        $this->assertEqualsWithDelta(7.0, (float) $tier20['qty'], 0.0001);
    }

    public function test_unallocated_warehouse_qty_attributed_to_base_price_tier(): void
    {
        $item = $this->stockItem('RM-TIER-BASE', qty: 10);
        $item->update(['price' => 1000.00]);

        // أسعار كتالوج مسجّلة بدون دفعات استلام
        $item->prices()->create([
            'price_ref' => 'PR-CAT-1',
            'amount' => 3000.00,
            'qty' => 0,
        ]);
        $item->prices()->create([
            'price_ref' => 'PR-CAT-2',
            'amount' => 2000.00,
            'qty' => 0,
        ]);

        $tiers = collect(app(StockCatalogService::class)->aggregatePriceTiers($item->fresh(['prices'])));
        $base = $tiers->firstWhere('amount', 1000.0);

        $this->assertNotNull($base);
        $this->assertEqualsWithDelta(10.0, (float) $base['qty'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $tiers->firstWhere('amount', 3000.0)['qty'], 0.0001);
    }
}
