<?php

namespace Tests\Feature\Reports;

use App\Models\CaseRecord;
use App\Models\Quote;
use Tests\Support\ProstheticTestHelper;
use Tests\Support\StubsBiReportForOverview;
use Tests\TestCase;

class AdminOverviewOperationsTest extends TestCase
{
    use ProstheticTestHelper;
    use StubsBiReportForOverview;

    public function test_overview_operations_count_increases_when_case_enters_operations_desk(): void
    {
        $this->stubBiReportServiceForOverview();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $this->get('/admin/overview')
            ->assertOk()
            ->assertSee('id="overviewWaitingCount" class="overview-case-link__count" data-server-rendered="1">0</span>', false);

        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);
        Quote::create([
            'case_id' => $case->id,
            'quote_no' => 'QT-OPS-OVERVIEW',
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'status_label' => 'صادر للجهة — بانتظار خطاب الموافقة',
            'total' => 1000,
        ]);

        $this->get('/admin/overview')
            ->assertOk()
            ->assertSee('id="overviewWaitingCount" class="overview-case-link__count" data-server-rendered="1">1</span>', false);
    }
}
