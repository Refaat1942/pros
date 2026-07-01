<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\AdminCycleDashboardService;
use App\Services\BomService;
use Database\Seeders\RolesAndAdminSeeder;
use Tests\Support\ProstheticTestHelper;
use Tests\Support\StubsBiReportForOverview;
use Tests\TestCase;

class AdminCycleDashboardTest extends TestCase
{
    use ProstheticTestHelper;
    use StubsBiReportForOverview;

    public function test_cycle_service_counts_cases_per_dashboard_queue(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);

        Appointment::create([
            'patient_id'       => $patient->id,
            'patient_name'     => $patient->name,
            'phone'            => $patient->phone,
            'patient_type'     => $patient->patient_type,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '09:00',
            'status'           => Appointment::STATUS_WAITING,
            'transferred_to_clinic' => false,
        ]);

        Appointment::create([
            'patient_id'       => $patient->id,
            'patient_name'     => $patient->name,
            'phone'            => $patient->phone,
            'patient_type'     => $patient->patient_type,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00',
            'status'           => Appointment::STATUS_IN_CLINIC,
            'transferred_to_clinic' => true,
        ]);

        $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);
        $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);
        $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $from = now()->subDays(30)->startOfDay();
        $to   = now()->addDay()->endOfDay();
        $cards = collect(app(AdminCycleDashboardService::class)->build($from, $to))->keyBy('key');

        $this->assertSame(1, $cards['reception']['count']);
        $this->assertSame(1, $cards['doctor']['count']);
        $this->assertGreaterThanOrEqual(1, $cards['spec']['count']);
        $this->assertGreaterThanOrEqual(1, $cards['adjustments']['count']);
        $this->assertGreaterThanOrEqual(1, $cards['operations']['count']);
    }

    public function test_overview_page_shows_cycle_dashboard_cards(): void
    {
        $this->seed(RolesAndAdminSeeder::class);
        $admin = $this->userWithRole('admin');

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-CYCLE-01']);

        $item = $this->stockItem('RM-CYCLE', qty: 20);
        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => $item->code, 'qty' => 1],
        ]);

        $this->stubBiReportServiceForOverview([
            'item_count'     => 1,
            'low_stock'      => 0,
            'stagnant_items' => [],
            'total_value'    => 0,
        ], [
            'open_work_orders' => 0, 'awaiting_dispense' => 1, 'in_workshop' => 0, 'ready_for_delivery' => 0,
        ]);

        $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk()
            ->assertSee('overview-date-filter', false)
            ->assertSee('تصدير Excel', false)
            ->assertSee('دورة العمل — الطوابير الحية', false)
            ->assertSee('overview-cycle-grid', false)
            ->assertSee('الاستقبال')
            ->assertSee('عيادة الطبيب')
            ->assertSee('التوصيف الفني')
            ->assertSee('المعدلات')
            ->assertSee('مكتب التشغيل')
            ->assertSee('ورشة التصنيع')
            ->assertSee('المخزن')
            ->assertDontSee('أوامر التحضير المعلقة', false)
            ->assertDontSee('أكثر المرضى زيارة', false)
            ->assertDontSee('id="employeesTable"', false)
            ->assertDontSee('id="auditPreview"', false);
    }

    public function test_overview_export_respects_date_filter(): void
    {
        $this->seed(RolesAndAdminSeeder::class);
        $admin = $this->userWithRole('admin');

        $this->stubBiReportServiceForOverview();

        $from = now()->startOfMonth()->toDateString();
        $to   = now()->toDateString();

        $this->actingAs($admin)
            ->get('/admin/overview/export?from=' . $from . '&to=' . $to)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('نظرة_عامة_' . $from . '_' . $to . '.csv');
    }
}
