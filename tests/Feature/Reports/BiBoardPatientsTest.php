<?php

namespace Tests\Feature\Reports;

use App\Models\CaseRecord;
use App\Services\BiReportService;
use Tests\Support\ProstheticTestCase;

/**
 * لوحة «المرضى و SLA» تعمل على أي قاعدة بيانات — كانت تستخدم DATEDIFF/CURDATE (MySQL فقط)
 * فتسقط صفحة «نظرة عامة» على PostgreSQL. اختبارات أخرى تستبدل اللوحة ببيانات ثابتة.
 */
class BiBoardPatientsTest extends ProstheticTestCase
{
    public function test_turnaround_and_sla_breaches_are_computed_without_mysql_functions(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());

        $delivered = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $delivered->update(['quote_date' => now()->subDays(10)->toDateString(), 'delivered_at' => now()->subDays(4)]);

        $late = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING);
        $late->update(['quote_date' => now()->subDays(40)->toDateString()]);

        $board = app(BiReportService::class)->boardPatients();

        $this->assertEqualsWithDelta(6.0, $board['avg_turnaround'], 0.01);
        $this->assertSame(1, $board['sla_breached']);
        $this->assertSame($late->case_no, $board['sla_breached_cases'][0]['case_no']);
    }
}
