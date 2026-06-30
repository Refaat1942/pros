<?php

namespace Tests\Feature\Inventory;

use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\SupplierDebt;
use App\Services\StockReceiveService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SupplierCrudTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_create_supplier_with_extended_fields(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.suppliers.store'), [
                'name'                => 'مورد اختبار',
                'phone'               => '01012345678',
                'fax'                 => '0227654321',
                'address'             => 'القاهرة — مدينة نصر',
                'tax_number'          => '123-456-789',
                'commercial_registry' => 'CR-9988',
                'bank_name'           => 'البنك الأهلي',
                'bank_branch'         => 'فرع مدينة نصر',
                'bank_account'        => '1234567890',
                'iban'                => 'EG123456789012345678901234',
            ])
            ->assertCreated();

        $supplier = Supplier::where('name', 'مورد اختبار')->first();
        $this->assertNotNull($supplier);
        $this->assertSame('123-456-789', $supplier->tax_number);
    }

    public function test_catalog_item_links_supplier_on_create(): void
    {
        $admin    = $this->userWithRole('admin');
        $supplier = Supplier::create(['name' => 'مورد للصنف']);

        $this->actingAs($admin)
            ->postJson(route('admin.catalog.store'), [
                'name'         => 'صنف مرتبط بمورد',
                'code'         => 'ITM-SUP-LINK',
                'qty'          => 0,
                'price'        => 100,
                'supplier_ids' => [$supplier->id],
            ])
            ->assertCreated()
            ->assertJsonPath('item.suppliers.0.id', $supplier->id);

        $item = StockItem::where('code', 'ITM-SUP-LINK')->first();
        $this->assertNotNull($item);
        $this->assertTrue($supplier->fresh()->stockItems()->where('stock_items.id', $item->id)->exists());
    }

    public function test_catalog_item_rejects_multiple_suppliers(): void
    {
        $admin     = $this->userWithRole('admin');
        $supplier1 = Supplier::create(['name' => 'مورد 1']);
        $supplier2 = Supplier::create(['name' => 'مورد 2']);

        $this->actingAs($admin)
            ->postJson(route('admin.catalog.store'), [
                'name'         => 'صنف بموردين',
                'code'         => 'ITM-MULTI-SUP',
                'qty'          => 0,
                'price'        => 100,
                'supplier_ids' => [$supplier1->id, $supplier2->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_ids']);
    }

    public function test_receive_increases_supplier_debt_and_links_item(): void
    {
        $user     = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('ITM-SUP-002');

        app(StockReceiveService::class)->receive(
            $item, 4, 250.00, $supplier, 'INV-SUP-1', now(), $user
        );

        $debt = SupplierDebt::where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($debt);
        $this->assertSame(1000.0, (float) $debt->due);
        $this->assertTrue($supplier->fresh()->stockItems()->where('stock_items.id', $item->id)->exists());
    }

    public function test_cannot_delete_supplier_with_movements(): void
    {
        $admin    = $this->userWithRole('admin');
        $user     = $this->userWithRole('technical');
        $supplier = $this->makeSupplier();
        $item     = $this->stockItem('ITM-SUP-003');

        app(StockReceiveService::class)->receive(
            $item, 1, 100.00, $supplier, 'INV-SUP-2', now(), $user
        );

        $this->actingAs($admin)
            ->deleteJson(route('admin.suppliers.destroy', $supplier))
            ->assertStatus(422);

        $this->assertNull($supplier->fresh()->deleted_at);
    }

    public function test_can_soft_delete_supplier_without_financial_activity(): void
    {
        $admin    = $this->userWithRole('admin');
        $supplier = Supplier::create(['name' => 'مورد للحذف']);

        $this->actingAs($admin)
            ->deleteJson(route('admin.suppliers.destroy', $supplier))
            ->assertOk();

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_export_suppliers_csv(): void
    {
        $admin = $this->userWithRole('admin');
        Supplier::create(['name' => 'مورد تصدير', 'phone' => '01011112222']);

        $response = $this->actingAs($admin)
            ->get(route('admin.suppliers.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('تقرير الموردين', $response->streamedContent());
    }
}
