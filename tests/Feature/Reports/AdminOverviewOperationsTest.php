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
        $response->assertSee('ops-overview-bom-btn', false);
        $response->assertSee('opsOverviewBomModal', false);
    }

    public function test_overview_bom_items_modal_uses_merged_quantities_for_duplicate_codes(): void
    {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardPatients')->once()->andReturn([
            'open_count' => 0, 'sla_breached' => 0, 'sla_breached_cases' => [],
        ]);
        $mock->shouldReceive('boardInventory')->once()->andReturn([
            'item_count' => 0, 'low_stock' => 0,
        ]);

        $this->stockItem('RM-002', qty: 10);
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-ADM-001']);

        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
            ['stock_item_code' => 'RM-002', 'qty' => 1],
        ]);

        \App\Models\BomItem::create([
            'bom_id'          => $bom->id,
            'stock_item_code' => 'RM-002',
            'name'            => 'مكوّن إضافي',
            'source'          => \App\Models\BomItem::SOURCE_ADJUSTMENT,
            'qty'             => 1,
            'unit_cost'       => 0,
            'issued_qty'      => 0,
            'returned_qty'    => 0,
        ]);

        app(BomService::class)->releaseToWip($bom->fresh(['items']), ['BC-RM-001', 'BC-RM-002']);

        $response = $this->actingAs($admin)->get('/admin/overview');

        $response->assertOk()
            ->assertSee('data-items=', false)
            ->assertSee('"stock_item_code":"RM-002"', false)
            ->assertSee('"qty":2', false);
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
