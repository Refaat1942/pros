<?php

namespace Tests\Feature\Reports;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\StockItemPrice;
use App\Services\AdminReportsService;
use App\Services\Dashboard\DashboardPageDataService;
use App\Services\StockPriceService;
use Database\Seeders\RolesAndAdminSeeder;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminReportsPageTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_reports_service_returns_financial_and_bom_data(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update([
            'work_order_no' => 'WO-RPT-001',
            'quote_total'   => 5000,
            'delivered_at'  => now(),
            'paid'          => 5000,
        ]);

        $bom = Bom::create([
            'case_id'      => $case->id,
            'bom_no'       => 'BOM-RPT-01',
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'stage'        => Bom::STAGE_FINISHED,
        ]);

        BomItem::create([
            'bom_id'          => $bom->id,
            'stock_item_code' => 'RM-001',
            'name'            => 'صنف RM-001',
            'qty'             => 2,
            'unit_cost'       => 100.00,
        ]);

        $reports = app(AdminReportsService::class)->build();

        $this->assertGreaterThanOrEqual(5000, $reports['financial']['monthly_revenue']);
        $this->assertNotEmpty($reports['financial']['top_items']);
        $this->assertSame(1, $reports['bom']['summary'][Bom::STAGE_FINISHED]['count']);
        $this->assertCount(1, $reports['bom']['rows']);
    }

    public function test_admin_reports_page_renders_server_data(): void
    {
        $this->seed(RolesAndAdminSeeder::class);
        $admin = $this->userWithRole('admin');

        $item = $this->stockItem('RM-050', qty: 10, wac: 80.00);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 10, 200.00, $supplier, 'INV-RPT', now());

        StockItemPrice::query()->where('stock_item_id', $item->id)->update(['qty' => 5]);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update([
            'quote_total'  => 12000,
            'delivered_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('data-server-rendered="1"', false)
            ->assertSee('الإيرادات الشهرية')
            ->assertSee('12,000.00')
            ->assertSee('صحة المخزون');
    }

    public function test_dashboard_page_data_includes_admin_reports(): void
    {
        $data = app(DashboardPageDataService::class)->resolve('admin', 'reports');

        $this->assertArrayHasKey('admin_reports', $data);
        $this->assertArrayHasKey('financial', $data['admin_reports']);
        $this->assertArrayHasKey('bom', $data['admin_reports']);
    }
}
