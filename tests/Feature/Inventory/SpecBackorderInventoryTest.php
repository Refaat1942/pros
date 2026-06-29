<?php

namespace Tests\Feature\Inventory;

use App\Models\StockItem;
use App\Services\Dashboard\DashboardPageDataService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SpecBackorderInventoryTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_inventory_overview_shows_negative_available_and_backorder_qty(): void
    {
        $item = $this->stockItem('RM-BO-01', qty: 0);
        $item->update(['reserved' => 3]);

        $data = app(DashboardPageDataService::class)->resolve('admin', 'inventory-overview');
        $row = collect($data['inventory_items'])->firstWhere('code', 'RM-BO-01');

        $this->assertNotNull($row);
        $this->assertSame(-3, $row->availableQty());
        $this->assertSame(3, $row->backorderQty());

        $stats = collect($data['inventory_overview_stats']);
        $this->assertSame('1', $stats->firstWhere('label', 'طلبات توريد')['value']);
    }

    public function test_technical_inventory_lists_backorder_status(): void
    {
        $item = $this->stockItem('RM-BO-02', qty: 1);
        $item->update(['reserved' => 4]);

        $user = $this->userWithRole('technical');

        $this->actingAs($user)
            ->getJson('/technical/inventory/list')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'RM-BO-02')
            ->assertJsonPath('data.0.available', -3)
            ->assertJsonPath('data.0.backorder', 3)
            ->assertJsonPath('data.0.status', 'backorder');
    }
}
