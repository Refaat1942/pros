<?php

namespace Tests\Feature\Stock;

use App\Services\StockCatalogService;
use App\Services\StockImportService;
use App\Support\StockCatalogPicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class UnifiedCatalogListTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    public function test_admin_technical_and_picker_share_same_catalog_count(): void
    {
        $this->stockItem('UNI-1', qty: 5);
        $this->stockItem('UNI-2', qty: 3);
        $this->stockItem('UNI-3', qty: 1);

        $catalogService = app(StockCatalogService::class);
        $technical = $this->userWithRole('technical');

        $this->assertSame(3, $catalogService->countAll());
        $this->assertCount(3, $catalogService->allItemsForUnifiedLists());
        $this->assertCount(3, $catalogService->listForTechnicalInventory($technical));

        $this->actingAs($technical);
        $pageData = app(\App\Services\Dashboard\DashboardPageDataService::class)->resolve('technical', 'inventory');
        $this->assertSame(3, $pageData['inventory_items_total'] ?? 0);
        $this->assertCount(3, $pageData['inventory_items']);

        $pickerItemCount = count(array_filter(
            StockCatalogPicker::rows(),
            fn (array $row) => ($row['type'] ?? 'item') !== 'kit',
        ));
        $this->assertSame(3, $pickerItemCount);
    }

    public function test_operational_list_uses_catalog_number_as_display_code(): void
    {
        $item = $this->stockItem('ITM-999', qty: 2);
        $item->update(['catalog_number' => '198', 'code' => 'ITM-999']);

        $row = app(StockCatalogService::class)->formatOperationalListRow($item->fresh());
        $this->assertSame('198', $row['code']);
        $this->assertSame('ITM-999', $row['internal_code']);
    }

    public function test_bulk_import_refreshes_picker_cache(): void
    {
        Cache::put('stock_catalog_picker_rows_v2', [['code' => 'stale', 'name' => 'قديم']], 300);

        $csv = StockImportService::headers();
        $contents = implode(',', $csv)."\r\n"
            ."RM-NEW,10,صنف جديد,,9001,قطعة,1,0,0,1\r\n";

        app(StockImportService::class)->import(
            UploadedFile::fake()->createWithContent('new.csv', $contents),
        );

        $this->assertFalse(Cache::has('stock_catalog_picker_rows_v2'));
        $rows = StockCatalogPicker::rows();
        $this->assertNotEmpty($rows);
        $this->assertSame('RM-NEW', collect($rows)->firstWhere('name', 'صنف جديد')['catalog_code'] ?? null);
    }
}
