<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Models\Quote;
use App\Services\AdminCaseTrackingService;
use App\Services\Dashboard\DashboardPageDataService;
use App\Support\ClinicTime;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminCaseTrackingTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_issued_quote_appears_in_waiting_return_bucket(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->operationsReadyCase($patient);
        $quote   = Quote::where('case_id', $case->id)->firstOrFail();
        $ops     = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/release-quote")
            ->assertOk();

        $buckets = app(AdminCaseTrackingService::class)->buckets();

        $this->assertSame(1, $buckets['counts']['waiting_return']);
        $row = $buckets['waiting_return']->first();
        $this->assertSame($patient->name, $row['patient']);
        $this->assertSame($quote->fresh()->quote_no, $row['quoteId']);
    }

    public function test_manufacturing_case_with_issued_quote_appears_in_waiting_return_bucket(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['quote_no' => 'QT-TEST-MFG', 'quote_date' => now()->toDateString()]);

        \App\Models\Quote::create([
            'case_id'       => $case->id,
            'quote_no'      => 'QT-TEST-MFG',
            'order_ref'     => $case->order_ref,
            'patient_name'  => $patient->name,
            'company_name'  => $case->company_name,
            'quote_date'    => now()->toDateString(),
            'status'        => \App\Models\Quote::STATUS_ISSUED,
            'status_label'  => 'صادر للجهة — بانتظار خطاب الموافقة',
            'total'         => 1000,
        ]);

        $buckets = app(AdminCaseTrackingService::class)->buckets();

        $this->assertSame(1, $buckets['counts']['waiting_return']);
        $this->assertSame(0, $buckets['counts']['in_progress']);
        $this->assertSame('QT-TEST-MFG', $buckets['waiting_return']->first()['quoteId']);
    }

    public function test_manufacturing_case_appears_in_in_progress_bucket(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_CASTING);
        $case->update([
            'approval_date' => now()->toDateString(),
            'work_order_no' => 'WO-2026-0822',
        ]);

        Bom::create([
            'case_id'      => $case->id,
            'bom_no'       => 'BOM-0099',
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'stage'        => Bom::STAGE_WIP,
        ]);

        $buckets = app(AdminCaseTrackingService::class)->buckets();

        $this->assertSame(1, $buckets['counts']['in_progress']);
        $this->assertCount(1, $buckets['in_progress']);

        $row = $buckets['in_progress']->first();
        $this->assertSame($patient->name, $row['patient']);
        $this->assertSame('casting', $row['manufacturingStage']);
        $this->assertSame('صب', $row['manufacturingLabel']);
        $this->assertSame(Bom::STAGE_WIP, $row['bom']['stage']);
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $row['approvalDate']);
    }

    public function test_approval_date_falls_back_to_confirmed_at(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_CASTING);
        $case->update([
            'approval_date'         => null,
            'approval_confirmed_at' => '2026-05-21 08:30:00',
        ]);

        $row = app(AdminCaseTrackingService::class)->buckets()['in_progress']->first();

        $this->assertSame('21/05/2026', $row['approvalDate']);
    }

    public function test_admin_cases_page_data_includes_buckets(): void
    {
        $patient = $this->militaryPatient($this->militaryCompany());
        $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_CASTING);

        $data = app(DashboardPageDataService::class)->resolve('admin', 'cases');

        $this->assertArrayHasKey('admin_case_buckets', $data);
        $this->assertGreaterThanOrEqual(1, count($data['admin_case_buckets']['in_progress']));
        $this->assertGreaterThanOrEqual(1, $data['admin_case_counts']['in_progress']);
        $this->assertArrayHasKey('case_date_from', $data);
        $this->assertArrayHasKey('case_date_to', $data);
    }

    public function test_cases_buckets_respect_date_range_filter(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['delivered_at' => now()->subMonths(2)]);

        $recent = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $recent->update(['delivered_at' => now()]);

        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $filtered = app(AdminCaseTrackingService::class)->buckets($from, $to);
        $all = app(AdminCaseTrackingService::class)->buckets();

        $this->assertGreaterThanOrEqual(2, $all['counts']['delivered']);
        $this->assertSame(1, $filtered['counts']['delivered']);
        $this->assertSame((string) $recent->id, $filtered['delivered']->first()['id'] ?? null);
    }

    public function test_admin_cases_page_shows_date_filter_and_patient_tracking_label(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/cases')
            ->assertOk()
            ->assertSee('متابعة المرضى', false)
            ->assertSee('cases-date-filter', false)
            ->assertSee('تطبيق الفترة', false);
    }

    public function test_case_row_includes_patient_phone_for_search(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);

        $row = app(AdminCaseTrackingService::class)->buckets()['delivered']
            ->firstWhere('id', (string) $case->id);

        $this->assertNotNull($row);
        $this->assertSame('01000000001', $row['patientPhone']);
    }

    public function test_delivered_bucket_includes_delivery_date_and_time(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $deliveredAt = now()->setTime(14, 30, 0);
        $case->update(['delivered_at' => $deliveredAt]);

        $row = app(AdminCaseTrackingService::class)->buckets()['delivered']
            ->firstWhere('id', (string) $case->id);

        $this->assertNotNull($row);
        $this->assertSame(ClinicTime::format($deliveredAt), $row['deliveredAt']);
    }

    public function test_delivered_bucket_shows_cost_from_bom_when_case_fields_empty(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update([
            'total_cost'    => 0,
            'quote_total'   => 0,
            'invoice_total' => 0,
            'paid'          => 0,
            'delivered_at'  => now()->toDateString(),
        ]);

        $bom = Bom::create([
            'case_id'      => $case->id,
            'bom_no'       => 'BOM-DEL-01',
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'stage'        => Bom::STAGE_FINISHED,
        ]);

        BomItem::create([
            'bom_id'          => $bom->id,
            'stock_item_code' => 'RM-001',
            'name'            => 'صنف RM-001',
            'qty'             => 3,
            'unit_cost'       => 200.00,
        ]);

        $row = app(AdminCaseTrackingService::class)->buckets()['delivered']->first();

        $this->assertSame(600.0, $row['totalCost']);
        $this->assertSame(600.0, $row['paid']);
    }

    public function test_delivered_bucket_shows_cost_from_pricing_request(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update([
            'total_cost'    => 0,
            'quote_total'   => 0,
            'invoice_total' => 0,
            'paid'          => 0,
            'delivered_at'  => now()->toDateString(),
        ]);

        PricingRequest::create([
            'case_id'        => $case->id,
            'request_no'     => 'PR-2026-0100',
            'computed_total' => 1800.00,
            'status_key'     => 'awaiting_admin_approval',
            'patient_type'   => 'civilian',
            'order_ref'      => $case->order_ref,
            'patient_name'   => $patient->name,
            'request_date'   => now()->toDateString(),
        ]);

        $row = app(AdminCaseTrackingService::class)->buckets()['delivered']->first();

        $this->assertSame(1800.0, $row['totalCost']);
        $this->assertSame(1800.0, $row['paid']);
    }

    public function test_cash_civilian_shows_dash_in_contract_company_column(): void
    {
        $patient = $this->cashPatient();
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['delivered_at' => now()]);

        $row = app(AdminCaseTrackingService::class)->buckets()['delivered']
            ->firstWhere('id', (string) $case->id);

        $this->assertNotNull($row);
        $this->assertSame('—', $row['company']);
    }

    public function test_contracted_civilian_shows_company_name_in_contract_company_column(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['delivered_at' => now()]);

        $row = app(AdminCaseTrackingService::class)->buckets()['delivered']
            ->firstWhere('id', (string) $case->id);

        $this->assertNotNull($row);
        $this->assertSame($patient->displayEntity(), $row['company']);
        $this->assertNotSame('—', $row['company']);
    }

    public function test_cash_patient_at_cashier_appears_in_awaiting_cashier_bucket_not_waiting_return(): void
    {
        $patient = $this->cashPatient();
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_CASHIER);
        $case->update(['quote_no' => 'QT-CASH-01', 'quote_date' => now()->toDateString()]);

        Quote::create([
            'case_id'      => $case->id,
            'quote_no'     => 'QT-CASH-01',
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_ISSUED,
            'status_label' => 'بانتظار الدفع في الخزنة',
            'total'        => 1500,
        ]);

        $buckets = app(AdminCaseTrackingService::class)->buckets();

        $this->assertSame(0, $buckets['counts']['waiting_return']);
        $this->assertSame(1, $buckets['counts']['awaiting_cashier']);
        $this->assertSame('QT-CASH-01', $buckets['awaiting_cashier']->first()['quoteId']);
    }
}
