<?php

namespace Tests\Feature\Reports;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\StockItemPrice;
use App\Services\AdminReportsHubService;
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

    public function test_general_view_page_renders_snapshot_data(): void
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
            ->get('/admin/general-view')
            ->assertOk()
            ->assertSee('data-server-rendered="1"', false)
            ->assertSee('رؤية عامة', false)
            ->assertSee('الإيرادات الشهرية')
            ->assertSee('صحة المخزون');
    }

    public function test_reports_hub_shows_section_cards(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('reports-hub-grid', false)
            ->assertSee('مسار المرضى', false)
            ->assertSee('سجل الرقابة', false);
    }

    public function test_reports_section_supports_date_filter_and_export(): void
    {
        $admin = $this->userWithRole('admin');
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($admin)
            ->get('/admin/reports/audit?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertSee('reports-date-filter', false)
            ->assertSee('تصدير Excel', false);

        $this->actingAs($admin)
            ->get('/admin/reports/audit/export?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_reports_hub_service_builds_financial_report_for_range(): void
    {
        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange(now()->startOfMonth()->toDateString(), now()->toDateString());

        $report = $hub->build('financial', $dates['from'], $dates['to']);

        $this->assertSame('الإيرادات والمالية', $report['title']);
        $this->assertNotEmpty($report['headers']);
    }

    public function test_reports_hub_does_not_list_internal_reports_section_page(): void
    {
        $hub = app(AdminReportsHubService::class);
        $ids = collect($hub->sections())->pluck('id')->all();

        $this->assertNotContains('reports-section', $ids);
        $this->assertNull($hub->sectionMeta('reports-section'));
    }

    public function test_reports_section_slug_returns_not_found(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/reports/reports-section')
            ->assertNotFound();
    }

    public function test_dashboard_page_data_includes_general_view_and_reports_hub(): void
    {
        $general = app(DashboardPageDataService::class)->resolve('admin', 'general-view');
        $hub = app(DashboardPageDataService::class)->resolve('admin', 'reports');

        $this->assertArrayHasKey('admin_reports', $general);
        $this->assertArrayHasKey('financial', $general['admin_reports']);
        $this->assertArrayHasKey('report_sections', $hub);
        $this->assertNotEmpty($hub['report_sections']);
    }
}
