<?php

namespace Tests\Feature\Inventory;

use App\Models\Permission;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\SupplierDebt;
use App\Models\SupplyRequestLine;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SupplyRequestNonCatalogTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_catalog_item_supply_request_still_works_without_stock_change(): void
    {
        $user = $this->userWithRole('technical');
        $item = $this->stockItem('RM-CAT-SR-01', qty: 5, wac: 10.00);
        $beforeQty = (int) $item->qty;
        $beforeStockCount = StockItem::query()->count();

        $this->actingAs($user);

        $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_CATALOG,
            'stock_item_id' => $item->id,
            'qty' => 3,
            'uom' => 'قطعة',
            'spec' => 'طلب توريد عادي',
        ])->assertCreated()
            ->assertJsonPath('line.line_type', SupplyRequestLine::TYPE_CATALOG);

        $item->refresh();
        $this->assertSame($beforeQty, (int) $item->qty);
        $this->assertSame($beforeStockCount, StockItem::query()->count());
        $this->assertDatabaseHas('supply_request_lines', [
            'stock_item_id' => $item->id,
            'line_type' => SupplyRequestLine::TYPE_CATALOG,
            'status' => SupplyRequestLine::STATUS_PENDING,
        ]);
    }

    public function test_non_catalog_supply_request_creates_no_stock_item(): void
    {
        $user = $this->userWithRole('technical');
        $beforeStockCount = StockItem::query()->count();

        $this->actingAs($user);

        $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'كرسي متحرك موديل X',
            'qty' => 2,
            'uom' => 'قطعة',
            'spec' => 'مقاس متوسط',
        ])->assertCreated();

        $this->assertSame($beforeStockCount, StockItem::query()->count());
        $this->assertDatabaseHas('supply_request_lines', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'كرسي متحرك موديل X',
            'stock_item_id' => null,
            'status' => SupplyRequestLine::STATUS_PENDING,
        ]);
    }

    public function test_non_catalog_supply_request_creates_no_stock_movement(): void
    {
        $user = $this->userWithRole('technical');
        $beforeMovements = StockMovement::query()->count();

        $this->actingAs($user);

        $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'مفصل ركبة صناعي',
            'qty' => 1,
        ])->assertCreated();

        $this->assertSame($beforeMovements, StockMovement::query()->count());
    }

    public function test_non_catalog_supply_request_has_no_accounting_impact(): void
    {
        $user = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $beforeDebt = (float) (SupplierDebt::query()->where('supplier_id', $supplier->id)->value('due') ?? 0);

        $this->actingAs($user);

        $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'صنف بدون محاسبة',
            'qty' => 5,
        ])->assertCreated();

        $afterDebt = (float) (SupplierDebt::query()->where('supplier_id', $supplier->id)->value('due') ?? 0);
        $this->assertSame($beforeDebt, $afterDebt);
    }

    public function test_non_catalog_request_rejects_empty_description(): void
    {
        $user = $this->userWithRole('technical');
        $this->actingAs($user);

        $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => '',
            'qty' => 1,
        ])->assertStatus(422);
    }

    public function test_non_catalog_request_appears_in_open_lines_list(): void
    {
        $user = $this->userWithRole('technical');
        $this->actingAs($user);

        $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'مقعد طبي خاص',
            'qty' => 1,
        ])->assertCreated();

        $this->getJson('/technical/supply/requests')
            ->assertOk()
            ->assertJsonFragment(['description' => 'مقعد طبي خاص'])
            ->assertJsonFragment(['line_type' => SupplyRequestLine::TYPE_NON_CATALOG]);
    }

    public function test_non_catalog_line_can_be_resolved_to_existing_catalog_item(): void
    {
        $user = $this->userWithRole('technical');
        $catalogItem = $this->stockItem('RM-EXIST-01', qty: 0, wac: 0);

        $this->actingAs($user);

        $create = $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'صنف للربط',
            'qty' => 2,
        ])->assertCreated();

        $lineId = (int) $create->json('line.id');

        $this->postJson("/technical/supply/requests/{$lineId}/resolve", [
            'stock_item_id' => $catalogItem->id,
        ])->assertOk()
            ->assertJsonPath('line.status', SupplyRequestLine::STATUS_RESOLVED)
            ->assertJsonPath('line.receivable_stock_item_id', $catalogItem->id);

        $this->assertDatabaseHas('supply_request_lines', [
            'id' => $lineId,
            'resolved_stock_item_id' => $catalogItem->id,
            'status' => SupplyRequestLine::STATUS_RESOLVED,
        ]);
    }

    public function test_add_catalog_item_then_resolve_same_non_catalog_line(): void
    {
        $user = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $permission = Permission::query()->where('slug', 'manage-inventory')->firstOrFail();
        $user->role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->unsetRelation('role');
        $this->actingAs($user->fresh());

        $create = $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'مفصل ركبة نوع X',
            'qty' => 3,
        ])->assertCreated();

        $lineId = (int) $create->json('line.id');

        $this->postJson('/technical/catalog', [
            'name' => 'مفصل ركبة نوع X — كتالوج',
            'code' => 'RM-NEW-NC-01',
            'price' => 200,
            'supplier_ids' => [$supplier->id],
        ])->assertCreated();

        $newItem = StockItem::query()->where('code', 'RM-NEW-NC-01')->firstOrFail();

        $this->postJson("/technical/supply/requests/{$lineId}/resolve", [
            'stock_item_id' => $newItem->id,
        ])->assertOk()
            ->assertJsonPath('line.id', $lineId)
            ->assertJsonPath('line.status', SupplyRequestLine::STATUS_RESOLVED)
            ->assertJsonPath('line.receivable_stock_item_id', $newItem->id);
    }

    public function test_resolved_request_received_via_canonical_receive_inbound_route(): void
    {
        $user = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $catalogItem = $this->stockItem('RM-RESOLVE-01', qty: 0, wac: 0);

        $this->actingAs($user);

        $create = $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'كرسي متحرك موديل X',
            'qty' => 4,
        ])->assertCreated();

        $lineId = (int) $create->json('line.id');

        $this->postJson("/technical/supply/requests/{$lineId}/resolve", [
            'stock_item_id' => $catalogItem->id,
        ])->assertOk();

        $this->postJson('/technical/receive/receive', [
            'stock_item_id' => $catalogItem->id,
            'qty' => 4,
            'unit_price' => 150.00,
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-NC-01',
            'moved_at' => now()->toDateString(),
            'supply_request_line_id' => $lineId,
        ])->assertCreated();

        $catalogItem->refresh();
        $this->assertSame(4, (int) $catalogItem->qty);
        $this->assertEquals(150.00, (float) $catalogItem->wac);

        $this->assertDatabaseHas('supply_request_lines', [
            'id' => $lineId,
            'status' => SupplyRequestLine::STATUS_RECEIVED,
            'resolved_stock_item_id' => $catalogItem->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $catalogItem->id,
            'movement_type' => StockMovement::TYPE_RECEIVE,
            'quantity' => 4,
        ]);
    }

    public function test_stock_increases_only_on_receive_not_on_request_creation(): void
    {
        $user = $this->userWithRole('technical');
        $item = $this->stockItem('RM-QTY-ONLY-01', qty: 7, wac: 10.00);

        $this->actingAs($user);

        $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_CATALOG,
            'stock_item_id' => $item->id,
            'qty' => 10,
        ])->assertCreated();

        $item->refresh();
        $this->assertSame(7, (int) $item->qty);
    }

    public function test_supply_line_marked_received_only_after_successful_receive(): void
    {
        $user = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-STATUS-01', qty: 0, wac: 0);

        $this->actingAs($user);

        $lineId = (int) $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_CATALOG,
            'stock_item_id' => $item->id,
            'qty' => 2,
        ])->json('line.id');

        $this->assertDatabaseHas('supply_request_lines', [
            'id' => $lineId,
            'status' => SupplyRequestLine::STATUS_PENDING,
        ]);

        $this->postJson('/technical/receive/receive', [
            'stock_item_id' => $item->id,
            'qty' => 2,
            'unit_price' => 50.00,
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-STATUS-01',
            'moved_at' => now()->toDateString(),
            'supply_request_line_id' => $lineId,
        ])->assertCreated();

        $this->assertDatabaseHas('supply_request_lines', [
            'id' => $lineId,
            'status' => SupplyRequestLine::STATUS_RECEIVED,
        ]);
    }

    public function test_failed_receive_with_mismatched_line_rolls_back_stock_changes(): void
    {
        $user = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $targetItem = $this->stockItem('RM-TARGET-01', qty: 1, wac: 10.00);
        $wrongItem = $this->stockItem('RM-WRONG-01', qty: 3, wac: 20.00);

        $this->actingAs($user);

        $lineId = (int) $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_CATALOG,
            'stock_item_id' => $targetItem->id,
            'qty' => 5,
        ])->json('line.id');

        $beforeMovements = StockMovement::query()->count();

        $this->postJson('/technical/receive/receive', [
            'stock_item_id' => $wrongItem->id,
            'qty' => 5,
            'unit_price' => 99.00,
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-FAIL-01',
            'moved_at' => now()->toDateString(),
            'supply_request_line_id' => $lineId,
        ])->assertStatus(422);

        $targetItem->refresh();
        $wrongItem->refresh();
        $this->assertSame(1, (int) $targetItem->qty);
        $this->assertSame(3, (int) $wrongItem->qty);
        $this->assertSame($beforeMovements, StockMovement::query()->count());

        $this->assertDatabaseHas('supply_request_lines', [
            'id' => $lineId,
            'status' => SupplyRequestLine::STATUS_PENDING,
        ]);
    }

    public function test_supply_request_page_has_no_receive_form(): void
    {
        $user = $this->userWithRole('technical');
        $this->actingAs($user);

        $this->get('/technical/supply-request')
            ->assertOk()
            ->assertSee('صنف غير مكود', false)
            ->assertSee('supplyRequestCreateForm', false)
            ->assertSee('supply-request-desk.js', false)
            ->assertSee('__SUPPLY_REQUEST_API', false)
            ->assertDontSee('id="inventoryReceiveForm"', false)
            ->assertDontSee('استلام وارد — تسجيل فاتورة توريد', false)
            ->assertDontSee('initCatalogPage', false);
    }

    public function test_receive_inbound_page_works_with_pending_lines_and_receive_form(): void
    {
        $user = $this->userWithRole('technical');
        $this->actingAs($user);

        $this->get('/technical/receive-inbound')
            ->assertOk()
            ->assertSee('استلام الوارد', false)
            ->assertSee('inventoryReceiveForm', false)
            ->assertSee('receivePendingLinesTable', false)
            ->assertSee('receive-inbound-desk.js', false)
            ->assertSee('__INVENTORY_RECEIVE_URL', false)
            ->assertSee('__RECEIVE_PENDING_LINES_URL', false);
    }

    public function test_supply_and_receive_pages_use_local_assets_not_cdn(): void
    {
        $user = $this->userWithRole('technical');
        $this->actingAs($user);

        $supplyHtml = $this->get('/technical/supply-request')->assertOk()->getContent();
        $receiveHtml = $this->get('/technical/receive-inbound')->assertOk()->getContent();

        foreach ([$supplyHtml, $receiveHtml] as $html) {
            $this->assertStringNotContainsString('cdn.jsdelivr', $html);
            $this->assertStringNotContainsString('unpkg.com', $html);
            $this->assertStringContainsString('/assets/', $html);
        }
    }

    public function test_supply_request_endpoints_require_supply_request_page_permission(): void
    {
        $user = $this->userWithRole('technical');
        $view = Permission::viewSlug('technical', 'supply-request');
        $user->role->permissions()->detach(
            Permission::query()->where('slug', $view)->pluck('id'),
        );
        $this->actingAs($user->fresh());

        $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'صنف',
            'qty' => 1,
        ])->assertForbidden();

        $this->getJson('/technical/supply/requests')->assertForbidden();
    }

    public function test_receive_route_requires_receive_inbound_page_not_supply_request(): void
    {
        $user = $this->userWithRole('technical');
        $item = $this->stockItem('RM-GATE-01', qty: 0, wac: 0);
        $supplier = $this->makeSupplier();

        $receiveView = Permission::viewSlug('technical', 'receive-inbound');
        $user->role->permissions()->detach(
            Permission::query()->where('slug', $receiveView)->pluck('id'),
        );
        $user->unsetRelation('role');
        $this->actingAs($user->fresh());

        $this->postJson('/technical/receive/receive', [
            'stock_item_id' => $item->id,
            'qty' => 1,
            'unit_price' => 10.00,
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-GATE',
            'moved_at' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_supply_request_does_not_change_reserved_qty(): void
    {
        $user = $this->userWithRole('technical');
        $item = $this->stockItem('RM-RESV-01', qty: 10, wac: 10.00);
        $item->update(['reserved' => 4]);
        $beforeReserved = (int) $item->reserved;

        $this->actingAs($user);

        $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_CATALOG,
            'stock_item_id' => $item->id,
            'qty' => 2,
        ])->assertCreated();

        $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'صنف بدون حجز',
            'qty' => 1,
        ])->assertCreated();

        $item->refresh();
        $this->assertSame($beforeReserved, (int) $item->reserved);
    }

    public function test_resolve_non_catalog_line_does_not_change_stock_qty_or_movements(): void
    {
        $user = $this->userWithRole('technical');
        $catalogItem = $this->stockItem('RM-RESOLVE-NOCHG', qty: 6, wac: 12.00);
        $beforeQty = (int) $catalogItem->qty;
        $beforeWac = (float) $catalogItem->wac;
        $beforeMovements = StockMovement::query()->count();

        $this->actingAs($user);

        $lineId = (int) $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'بند قبل الربط',
            'qty' => 3,
        ])->json('line.id');

        $this->postJson("/technical/supply/requests/{$lineId}/resolve", [
            'stock_item_id' => $catalogItem->id,
        ])->assertOk();

        $catalogItem->refresh();
        $this->assertSame($beforeQty, (int) $catalogItem->qty);
        $this->assertSame($beforeWac, (float) $catalogItem->wac);
        $this->assertSame($beforeMovements, StockMovement::query()->count());
    }

    public function test_receive_rejected_before_non_catalog_resolve(): void
    {
        $user = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $placeholderItem = $this->stockItem('RM-UNRESOLVED', qty: 2, wac: 15.00);
        $beforeQty = (int) $placeholderItem->qty;
        $beforeMovements = StockMovement::query()->count();

        $this->actingAs($user);

        $lineId = (int) $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'غير مكود بلا ربط',
            'qty' => 5,
        ])->json('line.id');

        $this->postJson('/technical/receive/receive', [
            'stock_item_id' => $placeholderItem->id,
            'qty' => 5,
            'unit_price' => 40.00,
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-UNRESOLVED',
            'moved_at' => now()->toDateString(),
            'supply_request_line_id' => $lineId,
        ])->assertStatus(422);

        $placeholderItem->refresh();
        $this->assertSame($beforeQty, (int) $placeholderItem->qty);
        $this->assertSame($beforeMovements, StockMovement::query()->count());

        $this->assertDatabaseHas('supply_request_lines', [
            'id' => $lineId,
            'status' => SupplyRequestLine::STATUS_PENDING,
            'resolved_stock_item_id' => null,
        ]);
    }

    public function test_resolve_endpoint_requires_supply_request_page_permission(): void
    {
        $user = $this->userWithRole('technical');
        $catalogItem = $this->stockItem('RM-RESOLVE-GATE', qty: 0, wac: 0);

        $this->actingAs($user);

        $lineId = (int) $this->postJson('/technical/supply/requests', [
            'line_type' => SupplyRequestLine::TYPE_NON_CATALOG,
            'description' => 'للاختبار',
            'qty' => 1,
        ])->json('line.id');

        $view = Permission::viewSlug('technical', 'supply-request');
        $user->role->permissions()->detach(
            Permission::query()->where('slug', $view)->pluck('id'),
        );
        $this->actingAs($user->fresh());

        $this->postJson("/technical/supply/requests/{$lineId}/resolve", [
            'stock_item_id' => $catalogItem->id,
        ])->assertForbidden();
    }
}
