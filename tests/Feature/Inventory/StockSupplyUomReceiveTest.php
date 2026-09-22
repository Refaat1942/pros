<?php

namespace Tests\Feature\Inventory;

use App\Models\StockMovement;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class StockSupplyUomReceiveTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_receive_in_supply_uom_converts_to_base_qty_and_wac(): void
    {
        $user = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-UOM-1', qty: 0, wac: 0);
        $item->update([
            'uom' => 'سم²',
            'supply_uom' => 'ورقة',
            'units_per_supply_unit' => 10000,
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/technical/inventory/receive', [
            'stock_item_id' => $item->id,
            'qty' => 2,
            'unit_price' => 500.00,
            'quantity_basis' => 'supply',
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-UOM-1',
            'moved_at' => now()->toDateString(),
        ]);

        $response->assertCreated();

        $item->refresh();
        $this->assertEquals(20000, (float) $item->qty);
        $this->assertEquals(0.05, (float) $item->wac);

        $movement = StockMovement::query()->where('stock_item_id', $item->id)->latest('id')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(20000, (float) $movement->quantity);
        $this->assertEquals(2, (float) $movement->supply_quantity);
        $this->assertEquals(0.05, (float) $movement->unit_cost);
        $this->assertEquals(500, (float) $movement->supply_unit_cost);
    }
}
