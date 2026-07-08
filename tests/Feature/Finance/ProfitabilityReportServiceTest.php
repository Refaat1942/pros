<?php

namespace Tests\Feature\Finance;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Services\ProfitabilityReportService;
use Carbon\Carbon;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ProfitabilityReportServiceTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_profitability_aggregates_revenue_cost_and_margin(): void
    {
        $from = Carbon::parse('2026-06-01');
        $to = Carbon::parse('2026-06-30');

        $company = $this->civilianCompany();
        $civPatient = $this->civilianPatient($company);
        $civCase = $this->caseAtStage($civPatient, CaseRecord::STAGE_DELIVERED);
        $civCase->update([
            'delivered_at' => '2026-06-10 10:00:00',
            'quote_total' => 1560,
            'internal_cost' => 600,
        ]);

        $mCompany = $this->militaryCompany();
        $mPatient = $this->militaryPatient($mCompany);
        $mCase = $this->caseAtStage($mPatient, CaseRecord::STAGE_DELIVERED);
        $mCase->update([
            'delivered_at' => '2026-06-12 10:00:00',
            'military_selling_price' => 2000,
            'internal_cost' => 800,
        ]);

        // حالة خارج الفترة — يجب استبعادها
        $outside = $this->caseAtStage($this->cashPatient(), CaseRecord::STAGE_DELIVERED);
        $outside->update(['delivered_at' => '2026-05-01 10:00:00', 'quote_total' => 500, 'internal_cost' => 100]);

        $report = app(ProfitabilityReportService::class)->report($from, $to);

        $this->assertCount(2, $report['cases']);
        $this->assertSame(3560.0, $report['totals']['revenue']);
        $this->assertSame(1400.0, $report['totals']['cost']);
        $this->assertSame(2160.0, $report['totals']['margin']);

        $this->assertSame(1560.0, $report['by_patient_type'][Patient::TYPE_CIVILIAN]['revenue']);
        $this->assertSame(960.0, $report['by_patient_type'][Patient::TYPE_CIVILIAN]['margin']);
        $this->assertSame(2000.0, $report['by_patient_type'][Patient::TYPE_MILITARY]['revenue']);
        $this->assertSame(60.0, $report['by_patient_type'][Patient::TYPE_MILITARY]['margin_pct']);

        $this->assertCount(2, $report['by_company']);
    }
}
