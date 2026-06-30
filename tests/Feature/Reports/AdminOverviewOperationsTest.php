<?php

namespace Tests\Feature\Reports;

use App\Models\CaseRecord;
use App\Models\Quote;
use App\Services\BiReportService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminOverviewOperationsTest extends TestCase
{
    use ProstheticTestHelper;

    private function stubBiReportServiceForOverview(
        int $requestCount = 1,
        array $inventoryBoard = ['item_count' => 0, 'low_stock' => 0, 'stagnant_items' => [], 'total_value' => 0],
        array $operationsBoard = ['open_work_orders' => 0, 'awaiting_dispense' => 0, 'in_workshop' => 0, 'ready_for_delivery' => 0],
    ): void {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardInventory')->times($requestCount)->andReturn($inventoryBoard);
        $mock->shouldReceive('boardOperations')->times($requestCount)->andReturn($operationsBoard);
    }

    public function test_overview_operations_count_increases_when_case_enters_operations_desk(): void
    {
        $this->stubBiReportServiceForOverview(2);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $this->get('/admin/overview')
            ->assertOk()
            ->assertSee('id="overviewWaitingCount" class="overview-case-link__count" data-server-rendered="1">0</span>', false);

        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);
        Quote::create([
            'case_id'      => $case->id,
            'quote_no'     => 'QT-OPS-OVERVIEW',
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_ISSUED,
            'status_label' => 'صادر للجهة — بانتظار خطاب الموافقة',
            'total'        => 1000,
        ]);

        $this->get('/admin/overview')
            ->assertOk()
            ->assertSee('id="overviewWaitingCount" class="overview-case-link__count" data-server-rendered="1">1</span>', false);
    }
}
