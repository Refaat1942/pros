<?php

namespace Tests\Feature\Inventory;

use App\Models\Permission;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\StockMovement;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class WarehouseSidebarNavigationTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_technical_supply_request_page_renders_without_receive_form(): void
    {
        $user = $this->userWithRole('technical');
        $this->actingAs($user);

        $this->get('/technical/supply-request')
            ->assertOk()
            ->assertSee('طلب التوريد', false)
            ->assertDontSee('id="inventoryReceiveForm"', false)
            ->assertDontSee('استلام وارد — تسجيل فاتورة توريد', false);
    }

    public function test_technical_receive_inbound_page_renders_receive_form(): void
    {
        $user = $this->userWithRole('technical');
        $this->actingAs($user);

        $this->get('/technical/receive-inbound')
            ->assertOk()
            ->assertSee('استلام الوارد', false)
            ->assertSee('inventoryReceiveForm', false)
            ->assertSee('__INVENTORY_RECEIVE_URL', false);
    }

    public function test_technical_add_catalog_item_requires_manage_inventory(): void
    {
        $user = $this->userWithRole('technical');
        $this->actingAs($user);

        $this->get('/technical/add-catalog-item')->assertForbidden();

        $permission = Permission::query()->where('slug', 'manage-inventory')->firstOrFail();
        $user->role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->unsetRelation('role');
        $this->actingAs($user->fresh());

        $this->get('/technical/add-catalog-item')
            ->assertOk()
            ->assertSee('إضافة صنف جديد', false)
            ->assertSee('catalogFormModal', false)
            ->assertSee('catalog-item-form.js', false);
    }

    public function test_technical_add_catalog_item_store_requires_manage_inventory(): void
    {
        $user = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();

        $this->actingAs($user);

        $this->postJson('/technical/catalog', [
            'name' => 'صنف من المخزن',
            'price' => 50,
            'supplier_ids' => [$supplier->id],
        ])->assertForbidden();

        $permission = Permission::query()->where('slug', 'manage-inventory')->firstOrFail();
        $user->role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->unsetRelation('role');
        $this->actingAs($user->fresh());

        $this->postJson('/technical/catalog', [
            'name' => 'صنف من المخزن',
            'price' => 50,
            'supplier_ids' => [$supplier->id],
        ])->assertCreated();
    }

    public function test_technical_receive_route_requires_receive_inbound_page(): void
    {
        $user = $this->userWithRole('technical');
        $item = $this->stockItem('RM-SUP-01', qty: 0, wac: 10.00);
        $supplier = $this->makeSupplier();

        $receiveView = Permission::viewSlug('technical', 'receive-inbound');
        $user->role->permissions()->detach(
            Permission::query()->where('slug', $receiveView)->pluck('id'),
        );

        $this->actingAs($user);

        $this->postJson('/technical/receive/receive', [
            'stock_item_id' => $item->id,
            'qty' => 2,
            'unit_price' => 12.5,
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-SUP-01',
            'moved_at' => now()->toDateString(),
        ])->assertForbidden();

        $user->role->permissions()->syncWithoutDetaching(
            Permission::query()->where('slug', $receiveView)->pluck('id'),
        );
        $user->unsetRelation('role');
        $this->actingAs($user->fresh());

        $this->postJson('/technical/receive/receive', [
            'stock_item_id' => $item->id,
            'qty' => 2,
            'unit_price' => 12.5,
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-SUP-01',
            'moved_at' => now()->toDateString(),
        ])->assertCreated();
    }

    public function test_admin_receive_inbound_page_renders_for_admin(): void
    {
        $admin = $this->userWithRole(Role::SLUG_ADMIN);
        $this->actingAs($admin);

        $this->get('/admin/receive-inbound')
            ->assertOk()
            ->assertSee('استلام الوارد', false)
            ->assertSee('inventoryReceiveForm', false);
    }

    public function test_admin_add_catalog_item_requires_manage_inventory(): void
    {
        $admin = $this->userWithRole(Role::SLUG_ADMIN);
        $limited = $this->userWithRole('technical');

        $this->actingAs($admin)
            ->get('/admin/add-catalog-item')
            ->assertOk();

        $this->actingAs($limited)
            ->get('/admin/add-catalog-item')
            ->assertForbidden();
    }

    public function test_admin_catalog_open_add_item_query_opens_entry_flow(): void
    {
        $admin = $this->userWithRole(Role::SLUG_ADMIN);
        $this->actingAs($admin);

        $this->get('/admin/catalog?open=add-item')
            ->assertOk()
            ->assertSee('openSlimCatalogForm', false);
    }

    public function test_technical_sidebar_shows_warehouse_nav_items_when_permitted(): void
    {
        $user = $this->userWithRole('technical');
        $permission = Permission::query()->where('slug', 'manage-inventory')->firstOrFail();
        $user->role->permissions()->syncWithoutDetaching([$permission->id]);

        $this->actingAs($user);

        $this->get('/technical/inventory')
            ->assertOk()
            ->assertSee(route('technical.add-catalog-item'), false)
            ->assertSee(route('technical.supply-request'), false)
            ->assertSee(route('technical.receive-inbound'), false)
            ->assertSee('إضافة صنف جديد', false)
            ->assertSee('طلب التوريد', false)
            ->assertSee('استلام الوارد', false);
    }

    public function test_created_catalog_item_can_be_received_via_receive_inbound(): void
    {
        $user = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();

        $permission = Permission::query()->where('slug', 'manage-inventory')->firstOrFail();
        $user->role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->unsetRelation('role');
        $this->actingAs($user->fresh());

        $createResponse = $this->postJson('/technical/catalog', [
            'name' => 'صنف جديد للاستلام',
            'price' => 80,
            'supplier_ids' => [$supplier->id],
        ]);
        $createResponse->assertCreated();

        $item = StockItem::query()->where('name', 'صنف جديد للاستلام')->firstOrFail();
        $this->assertSame(0, (int) $item->qty);

        $this->postJson('/technical/receive/receive', [
            'stock_item_id' => $item->id,
            'qty' => 4,
            'unit_price' => 85.00,
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-NEW-ITEM',
            'moved_at' => now()->toDateString(),
        ])->assertCreated();

        $item->refresh();
        $this->assertSame(4, (int) $item->qty);
        $this->assertEquals(85.00, (float) $item->wac);

        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id,
            'movement_type' => StockMovement::TYPE_RECEIVE,
            'quantity' => 4,
        ]);
    }

    public function test_add_catalog_item_page_does_not_load_full_catalog_table(): void
    {
        $user = $this->userWithRole('technical');
        $permission = Permission::query()->where('slug', 'manage-inventory')->firstOrFail();
        $user->role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->unsetRelation('role');
        $this->actingAs($user->fresh());

        $response = $this->get('/technical/add-catalog-item');
        $response->assertOk()
            ->assertDontSee('id="inventoryTable"', false)
            ->assertDontSee('initCatalogPage', false);
    }
}
