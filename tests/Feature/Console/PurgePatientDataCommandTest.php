<?php

namespace Tests\Feature\Console;

use App\Models\Patient;
use App\Services\AdminOverviewService;
use Database\Seeders\PatientSeeder;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgePatientDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_removes_patients_and_keeps_users(): void
    {
        $this->seed(RolesAndAdminSeeder::class);
        $this->seed(PatientSeeder::class);

        $this->assertGreaterThan(0, Patient::query()->count());

        $this->artisan('prosthetics:purge-patient-data --force')
            ->assertSuccessful();

        $this->assertSame(0, Patient::query()->count());
        $this->assertGreaterThan(0, \App\Models\User::query()->count());
    }

    public function test_purge_clears_admin_bi_boards_cache(): void
    {
        \Illuminate\Support\Facades\Cache::put(AdminOverviewService::BI_BOARDS_CACHE_KEY, [
            'board1' => ['total_cases' => 999],
        ], 300);

        $this->artisan('prosthetics:purge-patient-data --force')
            ->assertSuccessful()
            ->expectsOutputToContain('تم تحديث ذاكرة لوحات القيادة');

        $this->assertNull(
            \Illuminate\Support\Facades\Cache::get(AdminOverviewService::BI_BOARDS_CACHE_KEY),
        );
    }

    /** C-5: المسح يحفظ سجل الرقابة بالكامل (append-only) ولا يحذف أي صف. */
    public function test_purge_preserves_audit_log(): void
    {
        $this->seed(RolesAndAdminSeeder::class);
        $this->seed(PatientSeeder::class);

        // بذرة سجل رقابة بوسم مريض (كان يُحذف سابقاً).
        \App\Services\AuditService::log(
            action: 'create',
            description: 'سجل مريض للاختبار',
            tag: 'patients',
            after: ['x' => 1],
        );

        $auditBefore = \App\Models\AuditLog::count();
        $this->assertGreaterThan(0, $auditBefore);

        app(\App\Services\PatientDataPurgeService::class)->purge();

        // كل صفوف الرقابة السابقة باقية + صف جديد يوثّق المسح نفسه.
        $auditAfter = \App\Models\AuditLog::count();
        $this->assertGreaterThanOrEqual($auditBefore, $auditAfter);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purge']);
        $this->assertSame(0, Patient::query()->count());
    }
}
