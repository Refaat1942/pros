<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncNewPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_page_is_granted_to_its_role_without_restoring_revoked_ones(): void
    {
        $this->seed(RolesAndAdminSeeder::class);

        // سيرفر قديم: صفحة «طلب التوريد» أُضيفت بعد آخر مزامنة — لا صف صلاحية لها.
        Permission::query()->where('slug', 'technical.supply-request.view')->delete();

        // المدير سحب «إحصائيات الاستقبال» من دور الاستقبال عمداً.
        $reception = Role::query()->where('slug', Role::SLUG_RECEPTION)->firstOrFail();
        $stats = Permission::query()->where('slug', 'reception.statistics.view')->firstOrFail();
        $reception->permissions()->detach($stats->id);

        $this->artisan('prosthetics:sync-permissions')
            ->expectsOutputToContain('technical.supply-request.view')
            ->assertSuccessful();

        $technical = Role::query()->where('slug', Role::SLUG_TECHNICAL)->firstOrFail();
        $this->assertTrue($technical->permissions()->where('slug', 'technical.supply-request.view')->exists());
        $this->assertFalse($reception->permissions()->where('slug', 'reception.statistics.view')->exists());

        // تشغيل ثانٍ: لا جديد.
        $this->artisan('prosthetics:sync-permissions')
            ->expectsOutputToContain('لا جديد')
            ->assertSuccessful();
    }
}
