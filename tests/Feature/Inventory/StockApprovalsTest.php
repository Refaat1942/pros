<?php

namespace Tests\Feature\Inventory;

use App\Models\Permission;
use App\Models\StockMovement;
use App\Models\StockReceiptRequest;
use App\Models\SupplierDebt;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use App\Services\PermissionCatalogService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * اعتماد أدمن المخازن لطلبات التوريد واستلام الوارد:
 * من غير الصلاحية يفضل الطلب «بانتظار الاعتماد» ولا يتضاف رصيد؛ صاحب الصلاحية يمشي فوراً زي الأول.
 */
class StockApprovalsTest extends TestCase
{
    use ProstheticTestHelper;

    private function stockStaff(): User
    {
        $user = $this->userWithRole('technical');
        app(PermissionCatalogService::class)->seedRoleDefaults(true); // الافتراضي الحقيقي: بدون صلاحيات الاعتماد

        return $user->fresh(['role']);
    }

    private function storesAdmin(): User
    {
        $boss = User::create(['name' => 'أدمن المخازن', 'username' => 'stores_admin', 'password' => 'secret123', 'role_id' => $this->stockStaff()->role_id, 'status' => 'active']);
        // دور مستقل بالصلاحيات عشان مانديش أمين المخزن العادي نفس الصلاحية.
        $role = \App\Models\Role::create(['slug' => 'stores_admin_test', 'label_ar' => 'أدمن مخازن']);
        $role->permissions()->sync(Permission::whereIn('slug', ['approve-supply-requests', 'approve-stock-receipts', 'technical.stock-approvals.view', 'technical.supply-request.view', 'technical.receive-inbound.view'])->pluck('id'));
        $boss->update(['role_id' => $role->id]);

        return $boss->fresh(['role']);
    }

    private function receivePayload(int $itemId, int $supplierId, ?int $lineId = null): array
    {
        return array_filter([
            'stock_item_id' => $itemId, 'qty' => 10, 'unit_price' => 50, 'supplier_id' => $supplierId,
            'invoice_no' => 'INV-77', 'moved_at' => now()->toDateString(), 'supply_request_line_id' => $lineId,
        ], fn ($v) => $v !== null);
    }

    public function test_supply_request_from_staff_waits_for_approval_and_cannot_be_received(): void
    {
        $item = $this->stockItem('RM-001', qty: 2);
        $staff = $this->stockStaff();

        $this->actingAs($staff)->postJson('/technical/supply/requests/batch', [
            'lines' => [['line_type' => 'catalog', 'stock_item_id' => $item->id, 'qty' => 5]],
        ])->assertCreated();

        $request = SupplyRequest::firstOrFail();
        $this->assertSame(SupplyRequest::STATUS_PENDING_APPROVAL, $request->status);
        $line = $this->actingAs($staff)->getJson('/technical/supply/requests')->assertOk()->json('data.0');
        $this->assertFalse($line['can_receive']);
        $this->assertTrue($line['awaiting_approval']);

        // مافيش استلام على طلب لسه مش معتمد — حتى لو صاحب صلاحية الاستلام حاول.
        $supplier = $this->makeSupplier();
        $this->actingAs($this->userWithRole('admin'))->postJson('/admin/receive/receive', $this->receivePayload($item->id, $supplier->id, $request->lines()->first()->id))
            ->assertStatus(422);
        $this->assertEquals(2.0, (float) $item->fresh()->qty);
    }

    public function test_stores_admin_approves_or_rejects_supply_request(): void
    {
        $item = $this->stockItem('RM-001', qty: 2);
        $staff = $this->stockStaff();
        $boss = $this->storesAdmin();

        foreach ([1, 2] as $n) {
            $this->actingAs($staff)->postJson('/technical/supply/requests', ['line_type' => 'catalog', 'stock_item_id' => $item->id, 'qty' => $n])->assertCreated();
        }
        [$first, $second] = SupplyRequest::orderBy('id')->get()->all();

        $pending = $this->actingAs($boss)->getJson('/technical/stock-approvals/pending')->assertOk();
        $this->assertCount(2, $pending->json('supply_requests'));
        $this->assertTrue($pending->json('can_approve_supply'));

        // أمين المخزن العادي مايقدرش يعتمد.
        $this->actingAs($staff)->postJson("/technical/stock-approvals/supply/{$first->id}/approve")->assertForbidden();

        $this->actingAs($boss)->postJson("/technical/stock-approvals/supply/{$first->id}/approve")->assertOk();
        $this->assertSame(SupplyRequest::STATUS_OPEN, $first->fresh()->status);
        $this->assertSame($boss->id, $first->fresh()->approved_by_user_id);

        $this->actingAs($boss)->postJson("/technical/stock-approvals/supply/{$second->id}/reject", [])->assertStatus(422);
        $this->actingAs($boss)->postJson("/technical/stock-approvals/supply/{$second->id}/reject", ['reason' => 'مش محتاجينه'])->assertOk();
        $this->assertSame(SupplyRequest::STATUS_REJECTED, $second->fresh()->status);
        $this->assertSame(SupplyRequestLine::STATUS_CANCELLED, $second->lines()->first()->status);

        // المعتمد بقى قابل للاستلام، والمرفوض اختفى من القائمة.
        $lines = collect($this->actingAs($staff)->getJson('/technical/supply/requests')->json('data'));
        $this->assertCount(1, $lines);
        $this->assertTrue($lines->first()['can_receive']);
    }

    public function test_receipt_from_staff_adds_no_stock_until_approved(): void
    {
        $item = $this->stockItem('RM-001', qty: 2);
        $supplier = $this->makeSupplier();
        $staff = $this->stockStaff();
        $boss = $this->storesAdmin();
        $movementsBefore = StockMovement::count();

        $this->actingAs($staff)->postJson('/technical/receive/receive', $this->receivePayload($item->id, $supplier->id))
            ->assertStatus(202)->assertJsonPath('pending_approval', true);

        $this->assertEquals(2.0, (float) $item->fresh()->qty, 'الرصيد مايتغيرش قبل الاعتماد');
        $this->assertSame($movementsBefore, StockMovement::count());
        $this->assertSame(0, (int) SupplierDebt::where('supplier_id', $supplier->id)->sum('due'));
        $receipt = StockReceiptRequest::firstOrFail();
        $this->assertSame(StockReceiptRequest::STATUS_PENDING, $receipt->status);

        $this->actingAs($staff)->postJson("/technical/stock-approvals/receipts/{$receipt->id}/approve")->assertForbidden();
        $this->actingAs($boss)->postJson("/technical/stock-approvals/receipts/{$receipt->id}/approve")->assertOk();

        $this->assertEquals(12.0, (float) $item->fresh()->qty);
        $this->assertSame(StockReceiptRequest::STATUS_APPROVED, $receipt->fresh()->status);
        $movement = StockMovement::findOrFail($receipt->fresh()->stock_movement_id);
        $this->assertSame($staff->id, $movement->performed_by_user_id, 'الحركة باسم اللي استلم فعلاً');
        $this->assertEquals(500.0, (float) SupplierDebt::where('supplier_id', $supplier->id)->sum('due'));

        // مايتعتمدش مرتين.
        $this->actingAs($boss)->postJson("/technical/stock-approvals/receipts/{$receipt->id}/approve")->assertStatus(422);
        $this->assertEquals(12.0, (float) $item->fresh()->qty);
    }

    public function test_rejected_receipt_changes_nothing(): void
    {
        $item = $this->stockItem('RM-001', qty: 2);
        $supplier = $this->makeSupplier();
        $this->actingAs($this->stockStaff())->postJson('/technical/receive/receive', $this->receivePayload($item->id, $supplier->id))->assertStatus(202);
        $receipt = StockReceiptRequest::firstOrFail();

        $this->actingAs($this->storesAdmin())->postJson("/technical/stock-approvals/receipts/{$receipt->id}/reject", ['reason' => 'الكمية غلط'])->assertOk();
        $this->assertSame(StockReceiptRequest::STATUS_REJECTED, $receipt->fresh()->status);
        $this->assertEquals(2.0, (float) $item->fresh()->qty);
    }

    public function test_user_with_permission_works_immediately_as_before(): void
    {
        $item = $this->stockItem('RM-001', qty: 2);
        $supplier = $this->makeSupplier();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson('/admin/supply/requests', ['line_type' => 'catalog', 'stock_item_id' => $item->id, 'qty' => 3])->assertCreated();
        $this->assertSame(SupplyRequest::STATUS_OPEN, SupplyRequest::firstOrFail()->status);

        $this->actingAs($admin)->postJson('/admin/receive/receive', $this->receivePayload($item->id, $supplier->id, SupplyRequest::first()->lines()->first()->id))->assertCreated();
        $this->assertEquals(12.0, (float) $item->fresh()->qty);
        $this->assertSame(0, StockReceiptRequest::count());
    }

    public function test_approvals_can_be_switched_off_by_config(): void
    {
        config(['inventory.supply_requires_approval' => false, 'inventory.receipt_requires_approval' => false]);
        $item = $this->stockItem('RM-001', qty: 2);
        $staff = $this->stockStaff();

        $this->actingAs($staff)->postJson('/technical/receive/receive', $this->receivePayload($item->id, $this->makeSupplier()->id))->assertCreated();
        $this->assertEquals(12.0, (float) $item->fresh()->qty);
    }

    public function test_approval_permissions_are_off_for_stock_staff_and_on_for_admin_role_by_default(): void
    {
        $this->stockStaff();
        $admin = \App\Models\Role::firstOrCreate(['slug' => 'admin'], ['label_ar' => 'مسؤول النظام']);
        app(PermissionCatalogService::class)->seedRoleDefaults(true);

        $tech = \App\Models\Role::where('slug', 'technical')->firstOrFail()->permissions()->pluck('slug')->all();
        $this->assertNotContains('approve-supply-requests', $tech);
        $this->assertNotContains('approve-stock-receipts', $tech);
        $this->assertNotContains('technical.stock-approvals.view', $tech);

        $adminPerms = $admin->fresh()->permissions()->pluck('slug')->all();
        $this->assertContains('approve-supply-requests', $adminPerms);
        $this->assertContains('approve-stock-receipts', $adminPerms);
    }
}
