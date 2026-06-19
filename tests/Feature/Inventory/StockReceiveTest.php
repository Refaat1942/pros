<?php

namespace Tests\Feature\Inventory;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\StockReceiveService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Feature — Stock receive, WAC recalculation, movement audit
 * (الفصل الخامس: المخزن والرقابة الصارمة)
 */
class StockReceiveTest extends TestCase
{
    use ProstheticTestHelper;

    // ── HTTP endpoint tests ───────────────────────────────────────────────────

    public function test_technical_user_can_receive_stock_via_endpoint(): void
    {
        $user     = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-010', qty: 0, wac: 0);

        $this->actingAs($user);

        $response = $this->postJson('/technical/inventory/receive', [
            'stock_item_id' => $item->id,
            'qty'           => 10,
            'unit_price'    => 120.00,
            'supplier_id'   => $supplier->id,
            'invoice_no'    => 'INV-100',
            'moved_at'      => now()->toDateString(),
        ]);

        $response->assertCreated();

        $item->refresh();
        $this->assertEquals(10, $item->qty);
        $this->assertEquals(120.00, (float) $item->wac);
    }

    public function test_reception_user_cannot_access_inventory_receive(): void
    {
        $user     = $this->userWithRole('reception');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-011', qty: 0);

        $this->actingAs($user);

        $this->postJson('/technical/inventory/receive', [
            'stock_item_id' => $item->id,
            'qty'           => 5,
            'unit_price'    => 100.00,
            'supplier_id'   => $supplier->id,
            'invoice_no'    => 'INV-101',
            'moved_at'      => now()->toDateString(),
        ])->assertStatus(403);
    }

    // ── Validation tests ──────────────────────────────────────────────────────

    public function test_receive_requires_qty_greater_than_zero(): void
    {
        $user     = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-012', qty: 0);

        $this->actingAs($user);

        $this->postJson('/technical/inventory/receive', [
            'stock_item_id' => $item->id,
            'qty'           => 0,
            'unit_price'    => 100.00,
            'supplier_id'   => $supplier->id,
            'invoice_no'    => 'INV-102',
            'moved_at'      => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('qty');
    }

    public function test_receive_requires_unit_price_greater_than_zero(): void
    {
        $user     = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-013', qty: 0);

        $this->actingAs($user);

        $this->postJson('/technical/inventory/receive', [
            'stock_item_id' => $item->id,
            'qty'           => 5,
            'unit_price'    => 0,
            'supplier_id'   => $supplier->id,
            'invoice_no'    => 'INV-103',
            'moved_at'      => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_price');
    }

    // ── Service-level tests ───────────────────────────────────────────────────

    public function test_stock_movement_record_created_on_receive(): void
    {
        $user     = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-020', qty: 0, wac: 0);

        app(StockReceiveService::class)->receive($item, 5, 200.00, $supplier, 'INV-200', now(), $user);

        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id,
            'movement_type' => StockMovement::TYPE_RECEIVE,
            'quantity'      => 5,
        ]);
    }

    public function test_wac_recalculated_after_receive(): void
    {
        $user     = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-021', qty: 5, wac: 100.00);

        app(StockReceiveService::class)->receive($item, 5, 300.00, $supplier, 'INV-201', now(), $user);

        $item->refresh();
        // (5×100 + 5×300) / 10 = 2000/10 = 200
        $this->assertEquals(200.00, (float) $item->wac);
    }

    /** Technical dashboard must NOT expose WAC or unit_cost in movement list */
    public function test_movement_list_hides_unit_cost_from_technical_user(): void
    {
        $user     = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-022', qty: 0);

        app(StockReceiveService::class)->receive($item, 3, 150.00, $supplier, 'INV-202', now(), $user);

        $this->actingAs($user);

        $response = $this->getJson("/technical/inventory/{$item->id}/movements");

        $response->assertOk();
        $response->assertJsonMissingPath('data.*.unit_cost');
        $response->assertJsonMissingPath('data.*.wac');
    }

    public function test_receive_creates_price_batch_with_correct_qty(): void
    {
        $user     = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-023', qty: 0, wac: 0);

        app(StockReceiveService::class)->receive($item, 8, 175.00, $supplier, 'INV-203', now(), $user);

        $this->assertDatabaseHas('stock_item_prices', [
            'stock_item_id' => $item->id,
            'qty'           => 8,
            'amount'        => 175.00,
        ]);
    }

    public function test_stock_status_becomes_ok_after_receive_above_threshold(): void
    {
        $user     = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-024', qty: 0, wac: 0);
        $item->update(['status' => 'low']);

        app(StockReceiveService::class)->receive(
            $item, StockItem::LOW_QTY_THRESHOLD + 5, 100.00, $supplier, 'INV-204', now(), $user
        );

        $item->refresh();
        $this->assertEquals('ok', $item->status);
    }

    public function test_receive_audit_log_written(): void
    {
        $user     = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('RM-025', qty: 0);

        app(StockReceiveService::class)->receive($item, 5, 100.00, $supplier, 'INV-205', now(), $user);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'receive',
            'tag'    => 'warehouse',
        ]);
    }
}
