<?php

namespace Tests\Feature\Console;

use App\Models\Patient;
use App\Models\StockItem;
use Database\Seeders\PatientSeeder;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetOfflineEnvironmentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_offline_wipes_patients_and_stock_items(): void
    {
        $this->seed(RolesAndAdminSeeder::class);
        $this->seed(PatientSeeder::class);

        StockItem::query()->create([
            'code' => 'RM-OFF-1',
            'catalog_number' => 'RM-OFF-1',
            'name' => 'صنف أوفلاين',
            'uom' => 'قطعة',
            'qty' => 0,
            'reserved' => 0,
            'price' => 0,
            'wac' => 0,
            'status' => StockItem::STATUS_OK,
        ]);

        $this->assertGreaterThan(0, Patient::query()->count());
        $this->assertGreaterThan(0, StockItem::query()->count());

        $this->artisan('prosthetics:reset-offline --force')
            ->assertSuccessful()
            ->expectsOutputToContain('تم مسح بيانات التشغيل');

        $this->assertSame(0, Patient::query()->count());
        $this->assertSame(0, StockItem::query()->count());
    }

    public function test_reset_offline_also_clears_pending_receipts_and_item_swaps(): void
    {
        $this->seed(RolesAndAdminSeeder::class);
        $user = \App\Models\User::query()->firstOrFail();
        $supplier = \App\Models\Supplier::create(['name' => 'مورد أوفلاين']);
        $item = StockItem::query()->create([
            'code' => 'RM-OFF-2', 'name' => 'صنف عليه طلبات', 'uom' => 'قطعة',
            'qty' => 5, 'reserved' => 0, 'price' => 10, 'wac' => 10, 'status' => StockItem::STATUS_OK,
        ]);

        // صنف عليه استلام بانتظار الاعتماد وطلب تبديل — كانوا بيوقّفوا المسح.
        \App\Models\StockReceiptRequest::create([
            'status' => 'pending', 'stock_item_id' => $item->id, 'supplier_id' => $supplier->id,
            'qty' => 1, 'unit_price' => 10, 'quantity_basis' => 'base', 'invoice_no' => 'INV-OFF',
            'moved_at' => now(), 'requested_by_user_id' => $user->id,
        ]);
        \App\Models\StockSwapRequest::create([
            'swap_no' => 'SW-OFF-1', 'status' => 'pending', 'supplier_id' => $supplier->id,
            'out_stock_item_id' => $item->id, 'out_qty' => 1, 'new_item' => ['name' => 'بديل'],
            'in_qty' => 1, 'price_difference' => 0, 'requested_by_user_id' => $user->id,
        ]);

        $this->artisan('prosthetics:reset-offline --force')->assertSuccessful();

        $this->assertSame(0, StockItem::query()->count());
        $this->assertSame(0, \App\Models\StockReceiptRequest::query()->count());
        $this->assertSame(0, \App\Models\StockSwapRequest::query()->count());
        $this->assertSame(1, \App\Models\Supplier::query()->count(), 'الموردين بيفضلوا');
        $this->assertGreaterThan(0, \App\Models\User::query()->count(), 'المستخدمين بيفضلوا');
    }
}
