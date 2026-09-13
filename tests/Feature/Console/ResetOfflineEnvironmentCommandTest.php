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
}
