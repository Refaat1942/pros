<?php

namespace Tests\Feature\Reports;

use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Quote;
use App\Models\User;
use App\Services\BiReportService;
use App\Services\BomService;
use App\Services\QuoteService;
use App\Services\StockPriceService;
use Database\Seeders\RolesAndAdminSeeder;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminOverviewOperationsTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_overview_shows_active_operations_desk_cases(): void
    {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardPatients')->once()->andReturn([
            'open_count'         => 1,
            'sla_breached'       => 0,
            'sla_breached_cases' => [],
        ]);
        $mock->shouldReceive('boardInventory')->once()->andReturn([
            'item_count' => 1,
            'low_stock'  => 0,
        ]);

        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $admin   = $this->userWithRole('admin');

        // اعتماد مكتب التشغيل ثم صرف المخزن → حالة نشطة بأمر شغل.
        $case = $this->dispensedManufacturingCase($patient);
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);

        $this->actingAs($admin);
        $response = $this->get('/admin/overview');

        $response->assertOk();
        $response->assertSee('مكتب التشغيل — أوامر نشطة');
        $response->assertSee($case->work_order_no);
        $response->assertSee($patient->name);
    }

    public function test_overview_lists_all_employees_including_reception_and_operations(): void
    {
        $this->seed(RolesAndAdminSeeder::class);

        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardPatients')->once()->andReturn([
            'open_count' => 0, 'sla_breached' => 0, 'sla_breached_cases' => [],
        ]);
        $mock->shouldReceive('boardInventory')->once()->andReturn([
            'item_count' => 0, 'low_stock' => 0,
        ]);

        $admin = User::where('email', 'admin@clinic.com')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get('/admin/overview');

        $response->assertOk();
        $response->assertSee('موظف استقبال');
        $response->assertSee('مكتب عمليات');
        $response->assertSee('فني تكاليف');
        $response->assertSee('8 موظف');
    }

    public function test_overview_shows_audit_log_preview_from_database(): void
    {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardPatients')->once()->andReturn([
            'open_count' => 0, 'sla_breached' => 0, 'sla_breached_cases' => [],
        ]);
        $mock->shouldReceive('boardInventory')->once()->andReturn([
            'item_count' => 0, 'low_stock' => 0,
        ]);

        $admin = $this->userWithRole('admin');

        AuditLog::create([
            'user_id'     => $admin->id,
            'user_name'   => $admin->name,
            'action'      => 'create',
            'description' => 'اختبار سجل الرقابة في النظرة العامة',
            'tag'         => 'system',
            'logged_at'   => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/overview');

        $response->assertOk();
        $response->assertSee('اختبار سجل الرقابة في النظرة العامة', false);
        $response->assertSee('data-server-rendered="1"', false);
    }

    public function test_overview_operations_count_increases_when_case_enters_operations_desk(): void
    {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardPatients')->twice()->andReturn([
            'open_count' => 1, 'sla_breached' => 0, 'sla_breached_cases' => [],
        ]);
        $mock->shouldReceive('boardInventory')->twice()->andReturn([
            'item_count' => 0, 'low_stock' => 0,
        ]);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        // لا توجد حالات في مكتب التشغيل بعد.
        $this->get('/admin/overview')
            ->assertOk()
            ->assertSee('id="overviewWaitingCount" style="color:#d97706" data-server-rendered="1">0</span>', false);

        // دخول حالة لمكتب التشغيل يزيد العدّاد.
        $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $this->get('/admin/overview')
            ->assertOk()
            ->assertSee('id="overviewWaitingCount" style="color:#d97706" data-server-rendered="1">1</span>', false);
    }
}
