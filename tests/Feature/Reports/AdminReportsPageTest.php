<?php

namespace Tests\Feature\Reports;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\Patient;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Models\StockMovement;
use App\Services\AdminReportsHubService;
use App\Services\AdminReportsService;
use App\Services\BiReportService;
use App\Services\BomService;
use App\Services\Dashboard\DashboardPageDataService;
use App\Services\ReturnNoteService;
use App\Services\StockPriceService;
use Database\Seeders\RolesAndAdminSeeder;
use Tests\Support\ProstheticTestHelper;
use Tests\Support\StubsBiReportForOverview;
use Tests\TestCase;

class AdminReportsPageTest extends TestCase
{
    use ProstheticTestHelper;
    use StubsBiReportForOverview;

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

    public function test_overview_page_renders_merged_general_view_data(): void
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

        $this->stubBiReportServiceForOverview([
            'item_count'     => 1,
            'low_stock'      => 0,
            'stagnant_items' => [],
            'total_value'    => 0,
        ]);

        $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk()
            ->assertSee('data-server-rendered="1"', false)
            ->assertSee('overview-date-filter', false)
            ->assertSee('مسح الفلتر', false)
            ->assertSee('دورة العمل — الطوابير الحية', false)
            ->assertDontSee('overview-metrics-row', false)
            ->assertDontSee('المالية والإيرادات', false)
            ->assertSee('id="overview-bi"', false)
            ->assertSee('id="bi-board-1"', false);

        $this->actingAs($admin)
            ->get('/admin/general-view')
            ->assertRedirect('/admin/overview');
    }

    public function test_reports_hub_shows_section_cards(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('reports-hub-grid', false)
            ->assertSee('مسار المرضى', false)
            ->assertSee('سجل الرقابة', false)
            ->assertSee('reports-hub-card-label">المديونات', false)
            ->assertDontSee('/admin/reports/military-debts', false)
            ->assertDontSee('reports-hub-card-label">مديونيات مدنية', false);
    }

    public function test_companies_report_shows_entity_and_classification_columns(): void
    {
        $admin = $this->userWithRole('admin');
        $contracted = $this->civilianCompany('شركة التأمين الوطني');
        $contracted->update(['is_contracted' => true]);

        $cash = ContractCompany::create([
            'company_code'  => 'CO-CASH-RPT',
            'name'          => 'جهة مرجعية نقدية',
            'is_military'   => false,
            'is_contracted' => false,
        ]);

        $military = $this->militaryCompany('جهة دفاع جوي');

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);
        $report = $hub->build('companies', $dates['from'], $dates['to']);

        $this->assertSame(
            ['الكود', 'الاسم', 'النوع', 'الجهة', 'التصنيف'],
            $report['headers'],
        );

        $contractedRow = collect($report['rows'])->first(fn ($row) => ($row[1] ?? '') === 'شركة التأمين الوطني');
        $cashRow = collect($report['rows'])->first(fn ($row) => ($row[0] ?? '') === 'CO-CASH-RPT');
        $militaryRow = collect($report['rows'])->first(fn ($row) => ($row[1] ?? '') === 'جهة دفاع جوي');

        $this->assertNotNull($contractedRow);
        $this->assertSame('مدني متعاقد', $contractedRow[2]);
        $this->assertSame('شركة التأمين الوطني', $contractedRow[3]);
        $this->assertSame('مدني', $contractedRow[4]);

        $this->assertNotNull($cashRow);
        $this->assertSame('مدني نقدي', $cashRow[2]);
        $this->assertSame('جهات', $cashRow[4]);

        $this->assertNotNull($militaryRow);
        $this->assertSame('—', $militaryRow[2]);
        $this->assertSame(Patient::MILITARY_SOVEREIGN_ENTITY, $militaryRow[3]);
        $this->assertSame('عسكري', $militaryRow[4]);

        $this->actingAs($admin)
            ->get('/admin/reports/companies?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertSee('الجهة', false)
            ->assertSee('التصنيف', false)
            ->assertSee('مدني متعاقد', false)
            ->assertSee('مدني نقدي', false);
    }

    public function test_civilian_debts_report_uses_debts_title(): void
    {
        $admin = $this->userWithRole('admin');
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);
        $report = $hub->build('civilian-debts', $dates['from'], $dates['to']);

        $this->assertSame('المديونات', $report['title']);
        $this->assertSame(['التاريخ', 'الجهة', 'المبلغ'], $report['headers']);
        $this->assertNull($hub->sectionMeta('military-debts'));

        $this->actingAs($admin)
            ->get('/admin/reports/civilian-debts?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertSee('المديونات', false)
            ->assertDontSee('>سجّله<', false);

        $this->actingAs($admin)
            ->get('/admin/reports/military-debts?from=' . $from . '&to=' . $to)
            ->assertNotFound();
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
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update([
            'work_order_no'  => 'WO-FIN-RPT',
            'invoice_no'     => 'INV-2026-0100',
            'quote_total'    => 8000,
            'invoice_total'  => 8000,
            'internal_cost'  => 3200,
            'delivered_at'   => now(),
        ]);

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange(now()->startOfMonth()->toDateString(), now()->toDateString());

        $report = $hub->build('financial', $dates['from'], $dates['to']);

        $this->assertSame('الإيرادات والمالية', $report['title']);
        $this->assertSame(
            ['رقم الحالة', 'المريض', 'أمر التشغيل', 'الفاتورة', 'الإجمالي'],
            $report['headers'],
        );
        $this->assertSame([], $report['summary']);

        $row = $report['rows'][0] ?? [];
        $this->assertSame('INV-2026-0100', $row[3] ?? null);

        $admin = $this->userWithRole('admin');
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($admin)
            ->get('/admin/reports/financial?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertSee('الفاتورة', false)
            ->assertSee('INV-2026-0100', false)
            ->assertDontSee('reports-summary-grid', false)
            ->assertDontSee('عدد الطلبات', false)
            ->assertDontSee('إجمالي الإيراد', false)
            ->assertDontSee('إجمالي التكلفة', false)
            ->assertDontSee('>تاريخ التسليم<', false);
    }

    public function test_catalog_report_shows_multi_price_flag_and_view_action(): void
    {
        $admin = $this->userWithRole('admin');
        $item = $this->stockItem('RM-RPT-CAT', qty: 10, wac: 50.00);
        $supplier = $this->makeSupplier();

        app(\App\Services\StockPriceService::class)->addBatch($item, 5, 100.00, $supplier, 'INV-A', now());
        app(\App\Services\StockPriceService::class)->addBatch($item->fresh(), 5, 150.00, $supplier, 'INV-B', now());

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);
        $report = $hub->build('catalog', $dates['from'], $dates['to']);

        $this->assertContains('أسعار متعددة', $report['headers']);
        $this->assertNotEmpty($report['rows']);
        $this->assertStringContainsString('نعم', $report['rows'][0][5] ?? '');
        $this->assertSame($item->id, $report['row_actions'][0]['stock_item_id'] ?? null);

        $this->actingAs($admin)
            ->get('/admin/reports/catalog?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertSee('أسعار متعددة', false)
            ->assertSee('نعم (2 أسعار)', false)
            ->assertSee(route('admin.catalog', ['item' => $item->id]), false)
            ->assertSee('👁️ عرض', false);
    }

    public function test_inventory_overview_report_shows_item_name_and_signed_quantities(): void
    {
        $admin = $this->userWithRole('admin');
        $item = $this->stockItem('RM-MOVE-RPT', qty: 20, wac: 40.00);
        $item->update(['name' => 'ركبة تجريبية']);

        StockMovement::create([
            'stock_item_id' => $item->id,
            'movement_type' => StockMovement::TYPE_ISSUE,
            'quantity'      => -3,
            'unit_cost'     => 40.00,
            'balance_after' => 17,
            'moved_at'      => now(),
        ]);

        StockMovement::create([
            'stock_item_id'  => $item->id,
            'movement_type'  => StockMovement::TYPE_RETURN,
            'quantity'       => 2,
            'unit_cost'      => 40.00,
            'balance_after'  => 19,
            'reference_type' => 'return_note',
            'reference_id'   => 1,
            'moved_at'       => now(),
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);
        $report = $hub->build('inventory-overview', $dates['from'], $dates['to']);

        $this->assertSame('متابعة حركة الأصناف', $report['title']);
        $this->assertSame(['التاريخ', 'النوع', 'الكود', 'اسم الصنف', 'الكمية'], $report['headers']);

        $issueRow = collect($report['rows'])->first(fn ($row) => ($row[2] ?? '') === 'RM-MOVE-RPT' && ($row[1] ?? '') === 'صرف / بيع');
        $returnRow = collect($report['rows'])->first(fn ($row) => ($row[2] ?? '') === 'RM-MOVE-RPT' && ($row[1] ?? '') === 'ارتجاع من الورشة');

        $this->assertNotNull($issueRow);
        $this->assertSame('ركبة تجريبية', $issueRow[3]);
        $this->assertSame('3', $issueRow[4]);
        $this->assertNotNull($returnRow);
        $this->assertSame('-2', $returnRow[4]);

        $this->actingAs($admin)
            ->get('/admin/reports/inventory-overview?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertSee('متابعة حركة الأصناف', false)
            ->assertSee('اسم الصنف', false)
            ->assertSee('ركبة تجريبية', false)
            ->assertDontSee('>المرجع<', false);
    }

    public function test_inventory_report_shows_stagnant_and_active_items(): void
    {
        $admin = $this->userWithRole('admin');

        $stagnant = $this->stockItem('RM-STAG-RPT', qty: 12, wac: 40.00);
        $stagnant->update([
            'name'          => 'ركبة راكدة',
            'last_moved_at' => now()->subDays(200)->toDateString(),
            'status'        => StockItem::STATUS_OK,
        ]);

        $active = $this->stockItem('RM-ACT-RPT', qty: 8, wac: 55.00);
        $active->update([
            'name'          => 'قدم شغالة',
            'last_moved_at' => now()->subDays(10)->toDateString(),
            'status'        => StockItem::STATUS_OK,
        ]);

        $low = $this->stockItem('RM-LOW-RPT', qty: 2, wac: 30.00);
        $low->update([
            'name'          => 'بطانة منخفضة',
            'last_moved_at' => now()->subDays(5)->toDateString(),
            'status'        => StockItem::STATUS_LOW,
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);
        $report = $hub->build('inventory', $dates['from'], $dates['to']);

        $this->assertSame('تحليلات المخزون', $report['title']);
        $this->assertSame(
            ['الكود', 'اسم الصنف', 'الكمية', 'آخر حركة', 'الحالة'],
            $report['headers'],
        );
        $this->assertSame([], $report['summary']);

        $stagnantRow = collect($report['rows'])->first(fn ($row) => ($row[0] ?? '') === 'RM-STAG-RPT');
        $activeRow = collect($report['rows'])->first(fn ($row) => ($row[0] ?? '') === 'RM-ACT-RPT');
        $lowRow = collect($report['rows'])->first(fn ($row) => ($row[0] ?? '') === 'RM-LOW-RPT');

        $this->assertSame('راكدة', $stagnantRow[4] ?? null);
        $this->assertSame('شغالة', $activeRow[4] ?? null);
        $this->assertSame('منخفضة', $lowRow[4] ?? null);

        $this->actingAs($admin)
            ->get('/admin/reports/inventory?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertDontSee('reports-summary-grid', false)
            ->assertSee('ركبة راكدة', false)
            ->assertSee('قدم شغالة', false)
            ->assertDontSee('حركات صرف', false)
            ->assertDontSee('إجمالي الكميات المصروفة', false);
    }

    public function test_returns_report_shows_items_view_action(): void
    {
        $this->seedStockForReturnsReport();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $ops     = $this->userWithRole('operations');
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-RPT-RET']);

        $this->actingAs($ops);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);
        $bom->items()->update(['unit_cost' => 200]);
        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-001']);

        $note = app(ReturnNoteService::class)->create(
            $bom->fresh(),
            [['stock_item_code' => 'RM-001', 'qty' => 2, 'name' => 'صنف RM-001']],
            'فائض عن الحاجة',
            $ops,
        );

        $this->actingAs($this->userWithRole('technical'));
        app(ReturnNoteService::class)->complete($note, [
            ['line_id' => $note->lines->first()->id, 'barcode' => 'BC-RM-001', 'qty_returned' => 2],
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);
        $report = $hub->build('returns', $dates['from'], $dates['to']);

        $this->assertSame('طلبات الارتجاع', $report['title']);
        $this->assertNotEmpty($report['row_actions']);
        $this->assertTrue($report['row_actions'][0]['can_view_items'] ?? false);
        $this->assertSame('RM-001', $report['row_actions'][0]['lines'][0]['code'] ?? null);
        $this->assertSame(2, $report['row_actions'][0]['lines'][0]['qty_returned'] ?? null);

        $this->actingAs($admin)
            ->get('/admin/reports/returns?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertSee('عرض الأصناف', false)
            ->assertSee('openReportsReturnItems', false)
            ->assertSee($note->fresh()->return_no)
            ->assertSee('__REPORTS_RETURN_ITEMS', false)
            ->assertSee('"qty_returned":2', false)
            ->assertSee('فائض عن الحاجة', false)
            ->assertSee('تاريخ الاستلام', false);
    }

    public function test_returns_report_excludes_pending_warehouse_notes(): void
    {
        $this->seedStockForReturnsReport();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $ops     = $this->userWithRole('operations');
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);

        $this->actingAs($ops);
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        $bom->items()->update(['unit_cost' => 200]);
        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-001']);

        $note = app(ReturnNoteService::class)->create(
            $bom->fresh(),
            [['stock_item_code' => 'RM-001', 'qty' => 1, 'name' => 'صنف RM-001']],
            'بانتظار المخزن',
            $ops,
        );

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);
        $report = $hub->build('returns', $dates['from'], $dates['to']);

        $this->assertSame([], $report['rows']);

        $this->actingAs($admin)
            ->get('/admin/reports/returns?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertDontSee($note->return_no, false);
    }

    public function test_spec_edit_requests_report_renders_for_date_range(): void
    {
        $admin = $this->userWithRole('admin');
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);
        $report = $hub->build('spec-edit-requests', $dates['from'], $dates['to']);

        $this->assertSame('طلبات تعديل التوصيف', $report['title']);
        $this->assertSame(
            ['رقم الحالة', 'المريض', 'مرجع الطلب', 'الحالة', 'طلب بواسطة', 'البنود', 'التاريخ'],
            $report['headers'],
        );

        $this->actingAs($admin)
            ->get('/admin/reports/spec-edit-requests?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertSee('طلبات تعديل التوصيف', false)
            ->assertSee('reports-date-filter', false);
    }

    public function test_suppliers_report_renders_for_date_range(): void
    {
        $admin = $this->userWithRole('admin');
        $supplier = $this->makeSupplier(['name' => 'مورد تقرير الاختبار']);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);
        $report = $hub->build('suppliers', $dates['from'], $dates['to']);

        $this->assertSame('الموردون', $report['title']);
        $this->assertContains($supplier->name, array_column($report['rows'], 0));

        $this->actingAs($admin)
            ->get('/admin/reports/suppliers?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertSee('الموردون', false)
            ->assertSee($supplier->name, false)
            ->assertSee('reports-date-filter', false);
    }

    public function test_stock_categories_report_renders_for_date_range(): void
    {
        $admin = $this->userWithRole('admin');
        $category = StockCategory::create(['name' => 'قسم تقرير الاختبار']);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);
        $report = $hub->build('stock-categories', $dates['from'], $dates['to']);

        $this->assertSame('أقسام الأصناف', $report['title']);
        $this->assertSame([], $report['summary']);
        $this->assertContains($category->name, array_column($report['rows'], 0));

        $this->actingAs($admin)
            ->get('/admin/reports/stock-categories?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertSee('أقسام الأصناف', false)
            ->assertSee($category->name, false)
            ->assertDontSee('reports-summary-grid', false);
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

    public function test_overview_warehouse_issues_counts_abs_issue_movements(): void
    {
        $item = $this->stockItem('RM-ISSUE-RPT', qty: 20, wac: 40.00);

        StockMovement::create([
            'stock_item_id' => $item->id,
            'movement_type' => StockMovement::TYPE_ISSUE,
            'quantity'      => -3,
            'unit_cost'     => 40.00,
            'balance_after' => 17,
            'moved_at'      => now(),
        ]);

        $from = now()->startOfDay();
        $to   = now()->endOfDay();
        $reports = app(AdminReportsService::class)->build($from, $to);

        $this->assertSame(3, $reports['inventory']['issues_this_month']);
    }

    public function test_overview_warehouse_issues_falls_back_to_bom_released_items(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING);

        $bom = Bom::create([
            'case_id'      => $case->id,
            'bom_no'       => 'BOM-ISSUE-RPT',
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'stage'        => Bom::STAGE_WIP,
            'released_at'  => now(),
        ]);

        BomItem::create([
            'bom_id'          => $bom->id,
            'stock_item_code' => 'RM-001',
            'name'            => 'صنف RM-001',
            'qty'             => 2,
            'unit_cost'       => 100.00,
            'issued_qty'      => 2,
        ]);

        $from = now()->startOfDay();
        $to   = now()->endOfDay();
        $reports = app(AdminReportsService::class)->build($from, $to);

        $this->assertSame(2, $reports['inventory']['issues_this_month']);
    }

    public function test_overview_warehouse_issues_includes_dispense_via_release_to_wip(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $this->dispensedManufacturingCase($patient, ['RM-001']);

        $from = now()->startOfDay();
        $to   = now()->endOfDay();
        $reports = app(AdminReportsService::class)->build($from, $to);

        $this->assertGreaterThanOrEqual(1, $reports['inventory']['issues_this_month']);
    }

    private function seedStockForReturnsReport(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
