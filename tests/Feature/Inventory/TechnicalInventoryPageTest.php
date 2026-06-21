<?php

namespace Tests\Feature\Inventory;

use App\Models\StockItem;
use App\Services\Dashboard\DashboardPageDataService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class TechnicalInventoryPageTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_technical_inventory_page_data_includes_stock_items(): void
    {
        $item = $this->stockItem('RM-050', qty: 12, wac: 80.00);

        $data = app(DashboardPageDataService::class)->resolve('technical', 'inventory');

        $this->assertArrayHasKey('inventory_items', $data);
        $this->assertCount(1, $data['inventory_items']);
        $this->assertSame($item->code, $data['inventory_items'][0]['code']);
        $this->assertSame(12, $data['inventory_items'][0]['qty']);
        $this->assertSame(12, $data['inventory_items'][0]['available']);
    }

    public function test_technical_user_can_list_inventory_via_api(): void
    {
        $user = $this->userWithRole('technical');
        $this->stockItem('RM-051', qty: 7, wac: 90.00);

        $this->actingAs($user);

        $response = $this->getJson('/technical/inventory/list');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('data.0.code', 'RM-051');
        $response->assertJsonPath('data.0.qty', 7);
        $this->assertArrayNotHasKey('wac', $response->json('data.0'));
    }

    public function test_technical_inventory_page_renders_with_items(): void
    {
        $user = $this->userWithRole('technical');
        $this->stockItem('RM-052', qty: 15, wac: 100.00);

        $this->actingAs($user);

        $this->get('/technical/inventory')
            ->assertOk()
            ->assertSee('RM-052', false)
            ->assertSee('window.__INVENTORY_ITEMS', false);
    }
}
