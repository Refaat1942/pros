<?php

namespace Tests\Feature\Inventory;

use App\Models\Permission;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockSwapRequest;
use App\Models\SupplierDebt;
use App\Models\User;
use App\Services\PermissionCatalogService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/** تبديل صنف مع المورد: طلب واعتماد أدمن المخازن، خصم الخارج وإضافة البديل تلقائياً. */
class StockSwapTest extends TestCase
{
    use ProstheticTestHelper;

    private function storesAdmin(): User
    {
        $this->userWithRole('technical');
        app(PermissionCatalogService::class)->seedRoleDefaults(true);
        $role = Role::create(['slug' => 'stores_admin_swap', 'label_ar' => 'أدمن مخازن']);
        $role->permissions()->sync(Permission::whereIn('slug', ['manage-item-swaps', 'technical.stock-approvals.view', 'technical.supply-request.view'])->pluck('id'));

        return User::create(['name' => 'أدمن المخازن', 'username' => 'stores_swap', 'password' => 'secret123', 'role_id' => $role->id, 'status' => 'active'])->fresh(['role']);
    }

    public function test_swap_with_new_item_deducts_old_and_creates_new_on_approval_only(): void
    {
        $old = $this->stockItem('RM-001', qty: 10, wac: 100);
        $supplier = $this->makeSupplier();
        $boss = $this->storesAdmin();

        $this->actingAs($boss)->postJson('/technical/supply/swaps', [
            'supplier_id' => $supplier->id,
            'out_stock_item_id' => $old->id,
            'out_qty' => 4,
            'new_item' => ['name' => 'ركبة هيدروليك موديل 2027', 'uom' => 'قطعة', 'spec' => 'مقاس 24'],
            'in_qty' => 2,
            'price_difference' => 150,
        ])->assertCreated();

        // قبل الاعتماد: ولا خصم ولا إضافة.
        $swap = StockSwapRequest::firstOrFail();
        $this->assertSame(StockSwapRequest::STATUS_PENDING, $swap->status);
        $this->assertEquals(10.0, (float) $old->fresh()->qty);
        $this->assertFalse(StockItem::where('name', 'ركبة هيدروليك موديل 2027')->exists());

        $pending = $this->actingAs($boss)->getJson('/technical/stock-approvals/pending')->assertOk();
        $this->assertCount(1, $pending->json('swaps'));
        $this->assertTrue($pending->json('can_manage_swaps'));

        $this->actingAs($boss)->postJson("/technical/stock-approvals/swaps/{$swap->id}/approve")->assertOk();

        $this->assertEquals(6.0, (float) $old->fresh()->qty);
        $new = StockItem::where('name', 'ركبة هيدروليك موديل 2027')->firstOrFail();
        $this->assertEquals(2.0, (float) $new->qty);
        $this->assertSame('مقاس 24', $new->spec);
        // قيمة الخارج 4 × 100 = 400 + فرق 150 = 550 ÷ 2 = 275 للوحدة.
        $this->assertEquals(275.0, round((float) $new->wac, 2));
        $this->assertSame(1, StockMovement::where('movement_type', StockMovement::TYPE_SWAP_OUT)->where('quantity', -4)->count());
        $this->assertSame(1, StockMovement::where('movement_type', StockMovement::TYPE_SWAP_IN)->where('stock_item_id', $new->id)->count());
        $this->assertSame(0, StockMovement::where('movement_type', StockMovement::TYPE_RECEIVE)->count());
        $this->assertEquals(150.0, round((float) SupplierDebt::where('supplier_id', $supplier->id)->sum('due'), 2));

        $swap->refresh();
        $this->assertSame(StockSwapRequest::STATUS_APPROVED, $swap->status);
        $this->assertSame($new->id, $swap->in_stock_item_id);

        // مايتعتمدش مرتين.
        $this->actingAs($boss)->postJson("/technical/stock-approvals/swaps/{$swap->id}/approve")->assertStatus(422);
        $this->assertEquals(6.0, (float) $old->fresh()->qty);
    }

    public function test_swap_with_existing_item_and_no_price_difference_adds_no_debt(): void
    {
        $old = $this->stockItem('RM-001', qty: 5, wac: 80);
        $other = $this->stockItem('RM-002', qty: 1, wac: 80);
        $supplier = $this->makeSupplier();
        $boss = $this->storesAdmin();

        $this->actingAs($boss)->postJson('/technical/supply/swaps', [
            'supplier_id' => $supplier->id, 'out_stock_item_id' => $old->id, 'out_qty' => 2,
            'in_stock_item_id' => $other->id, 'in_qty' => 2,
        ])->assertCreated();
        $swap = StockSwapRequest::firstOrFail();
        $this->actingAs($boss)->postJson("/technical/stock-approvals/swaps/{$swap->id}/approve")->assertOk();

        $this->assertEquals(3.0, (float) $old->fresh()->qty);
        $this->assertEquals(3.0, (float) $other->fresh()->qty);
        $this->assertEquals(0.0, (float) SupplierDebt::where('supplier_id', $supplier->id)->sum('due'));
    }

    public function test_reject_changes_nothing_and_staff_without_permission_cannot_swap(): void
    {
        $old = $this->stockItem('RM-001', qty: 5);
        $supplier = $this->makeSupplier();
        $boss = $this->storesAdmin();

        // أمين مخزن عادي (افتراضي حقيقي): مايقدرش يطلب تبديل.
        $staff = User::create(['name' => 'أمين', 'username' => 'staff_swap', 'password' => 'secret123', 'role_id' => Role::where('slug', 'technical')->value('id'), 'status' => 'active']);
        $this->actingAs($staff)->postJson('/technical/supply/swaps', [
            'supplier_id' => $supplier->id, 'out_stock_item_id' => $old->id, 'out_qty' => 1,
            'new_item' => ['name' => 'بديل'], 'in_qty' => 1,
        ])->assertForbidden();

        // أكتر من المتاح مرفوض.
        $this->actingAs($boss)->postJson('/technical/supply/swaps', [
            'supplier_id' => $supplier->id, 'out_stock_item_id' => $old->id, 'out_qty' => 9,
            'new_item' => ['name' => 'بديل'], 'in_qty' => 1,
        ])->assertStatus(422);

        $this->actingAs($boss)->postJson('/technical/supply/swaps', [
            'supplier_id' => $supplier->id, 'out_stock_item_id' => $old->id, 'out_qty' => 1,
            'new_item' => ['name' => 'بديل'], 'in_qty' => 1,
        ])->assertCreated();
        $swap = StockSwapRequest::firstOrFail();
        $this->actingAs($boss)->postJson("/technical/stock-approvals/swaps/{$swap->id}/reject", ['reason' => 'المورد رفض'])->assertOk();

        $this->assertSame(StockSwapRequest::STATUS_REJECTED, $swap->fresh()->status);
        $this->assertEquals(5.0, (float) $old->fresh()->qty);
        $this->assertFalse(StockItem::where('name', 'بديل')->exists());
    }
}
