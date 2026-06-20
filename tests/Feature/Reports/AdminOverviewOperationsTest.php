<?php

namespace Tests\Feature\Reports;

use App\Models\CaseRecord;
use App\Models\User;
use App\Services\BiReportService;
use App\Services\BomService;
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
}
