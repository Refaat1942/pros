<?php

namespace Tests\Feature\Stock;

use App\Models\StockItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class CatalogScreenBarcodeTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    public function test_screen_barcode_page_loads_for_item_with_alt_codes_only(): void
    {
        $admin = $this->userWithRole('admin');
        $item = StockItem::create([
            'code' => 'ITM-SCREEN-1',
            'name' => 'هاردنر بودر',
            'alt_codes' => '617P37',
            'barcode' => null,
            'qty' => 0,
            'reserved' => 0,
            'wac' => 0,
            'status' => StockItem::STATUS_OK,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.catalog.screen-barcode', $item))
            ->assertOk()
            ->assertSee('هاردنر بودر', false)
            ->assertSee(StockItem::barcodeForOperationalCode('617P37'), false);
    }

    public function test_screen_barcode_shows_help_when_no_codes(): void
    {
        $admin = $this->userWithRole('admin');
        $item = StockItem::create([
            'code' => 'ITM-SCREEN-2',
            'name' => 'صنف بلا كود',
            'alt_codes' => null,
            'barcode' => null,
            'qty' => 0,
            'reserved' => 0,
            'wac' => 0,
            'status' => StockItem::STATUS_OK,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.catalog.screen-barcode', $item))
            ->assertOk()
            ->assertSee('لا يوجد باركود لهذا الصنف', false);
    }
}
