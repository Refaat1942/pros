<?php

namespace Tests\Feature\Inventory;

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

    public function test_stock_receive_clears_backorder_and_leaves_available_qty(): void
    {
        $item = $this->stockItem('RM-BO-03', qty: 0);
        $item->update(['reserved' => 5]);

        $this->assertSame(5, $item->fresh()->backorderQty());
        $this->assertSame(-5, $item->fresh()->availableQty());

        $supplier = $this->makeSupplier();
        $user = $this->userWithRole('technical');

        $this->actingAs($user)
            ->postJson('/technical/inventory/receive', [
                'stock_item_id' => $item->id,
                'supplier_id' => $supplier->id,
                'qty' => 10,
                'unit_price' => 100,
                'invoice_no' => 'INV-BO-03',
                'moved_at' => now()->toDateString(),
            ])
            ->assertCreated();

        $item->refresh();
        $this->assertSame(10, $item->qty);
        $this->assertSame(5, $item->reserved);
        $this->assertSame(0, $item->backorderQty());
        $this->assertSame(5, $item->availableQty());
    }
}
