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
        $tech    = $this->userWithRole('technical');
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_WAITING_RETURN);

        $this->actingAs($tech);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);

        $this->actingAs($admin);
        $response = $this->get('/admin/overview');

        $response->assertOk();
        $response->assertSee('مكتب التشغيل — أوامر نشطة');
        $response->assertSee($case->work_order_no);
        $response->assertSee($case->patient->name);
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

        $admin = User::where('email', 'admin@clinic.local')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get('/admin/overview');

        $response->assertOk();
        $response->assertSee('موظف استقبال');
        $response->assertSee('مكتب عمليات');
        $response->assertSee('7 موظف');
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

    public function test_overview_waiting_return_count_increases_after_quote_issued_to_entity(): void
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
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_WAITING_RETURN);
        $quote   = Quote::create([
            'quote_no'     => 'QT-2026-0099',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_PENDING,
            'total'        => 500.00,
        ]);

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $this->get('/admin/overview')
            ->assertOk()
            ->assertSee('id="overviewWaitingCount" style="color:#d97706" data-server-rendered="1">0</span>', false);

        app(QuoteService::class)->markIssued($quote);

        $this->get('/admin/overview')
            ->assertOk()
            ->assertSee('id="overviewWaitingCount" style="color:#d97706" data-server-rendered="1">1</span>', false);
    }
}
