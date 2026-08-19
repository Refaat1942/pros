<?php

namespace Tests\Feature\Inventory;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\StockItem;
use App\Services\BomService;
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
        $response->assertJsonPath('data.0.available', 7);
        $payload = $response->json('data.0');
        $this->assertArrayNotHasKey('wac', $payload);
        $this->assertArrayNotHasKey('price', $payload);
        $this->assertArrayNotHasKey('unit_cost', $payload);
        $this->assertArrayNotHasKey('highest_price', $payload);
    }

    public function test_technical_inventory_page_data_excludes_prices(): void
    {
        $user = $this->userWithRole('technical');
        $this->actingAs($user);

        $item = $this->stockItem('RM-053', qty: 4, wac: 250.00);
        $item->update(['price' => 999.00]);

        $data = app(DashboardPageDataService::class)->resolve('technical', 'inventory');
        $row = $data['inventory_items'][0] ?? [];

        $this->assertSame('RM-053', $row['code'] ?? null);
        $this->assertArrayNotHasKey('price', $row);
        $this->assertArrayNotHasKey('wac', $row);
        $this->assertArrayNotHasKey('unit_cost', $row);

        $visibility = app(\App\Services\CatalogListVisibilityService::class);
        $columns = $visibility->visibleColumnsForUser($user, 'technical_inventory');
        $this->assertNotContains('price', $columns);
        $this->assertNotContains('wac', $columns);
        $this->assertNotContains('unit_cost', $columns);
    }

    public function test_technical_bom_api_hides_unit_cost_from_production_user(): void
    {
        $item = $this->stockItem('RM-BOM-P', qty: 10, wac: 300.00);
        $user = $this->userWithRole('technical');
        $case = $this->caseAtStage(
            $this->civilianPatient($this->civilianCompany()),
            CaseRecord::STAGE_MANUFACTURING,
            CaseRecord::MFG_WAREHOUSE,
        );
        $case->update(['work_order_no' => 'WO-PRICE-HIDE']);

        $this->actingAs($user);
        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => $item->operationalCode(), 'qty' => 2],
        ]);
        $bom->items()->update(['unit_cost' => 888.88]);

        $response = $this->getJson('/technical/bom/'.$bom->id);

        $response->assertOk();
        $items = $response->json('items') ?? [];
        $this->assertNotEmpty($items);
        foreach ($items as $row) {
            $this->assertArrayNotHasKey('unit_cost', $row);
            $this->assertArrayNotHasKey('price', $row);
            $this->assertArrayNotHasKey('wac', $row);
        }
    }

    public function test_item_min_qty_triggers_low_status_when_available_drops(): void
    {
        $admin = $this->userWithRole('admin');
        $supplier = $this->makeSupplier();

        $this->actingAs($admin);

        $response = $this->postJson('/admin/catalog', [
            'name' => 'صنف بحد أدنى',
            'qty' => 12,
            'min_qty' => 10,
            'price' => 100,
            'supplier_ids' => [$supplier->id],
        ]);

        $response->assertCreated();
        $item = StockItem::query()->where('name', 'صنف بحد أدنى')->firstOrFail();
        $this->assertSame(StockItem::STATUS_OK, $item->status);

        $item->update(['qty' => 10, 'reserved' => 2]);
        $item->refresh();
        $item->recalculateAndSaveStatus();
        $item->refresh();

        $this->assertSame(StockItem::STATUS_LOW, $item->status);
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
