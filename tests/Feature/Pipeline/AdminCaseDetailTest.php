<?php

namespace Tests\Feature\Pipeline;

use App\Models\ApprovalContract;
use App\Models\CaseRecord;
use App\Models\MilitaryRank;
use App\Models\Patient;
use App\Models\Quote;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminCaseDetailTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_fetch_civilian_case_detail_with_quote_and_letter(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update([
            'quote_no'      => 'QT-2026-0099',
            'quote_total'   => 2000.00,
            'work_order_no' => 'WO-2026-0099',
            'delivered_at'  => now()->toDateString(),
        ]);

        $quote = Quote::create([
            'quote_no'     => 'QT-2026-0099',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_APPROVED,
            'total'        => 2000.00,
        ]);

        ApprovalContract::create([
            'contract_no'     => 'CTR-2026-0001',
            'case_id'         => $case->id,
            'quote_id'        => $quote->id,
            'patient_name'    => $patient->name,
            'company_name'    => $company->name,
            'approved_amount' => 2000.00,
            'approval_date'   => now()->toDateString(),
            'work_order_no'   => 'WO-2026-0099',
            'letter_path'     => 'approval_letters/test-letter.png',
            'letter_ref'      => 'LTR-001',
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/cases/' . $case->id . '/detail');

        $response->assertOk();
        $response->assertJsonPath('patient.name', $patient->name);
        $response->assertJsonPath('quote.quote_no', 'QT-2026-0099');
        $response->assertJsonPath('approval.contract_no', 'CTR-2026-0001');
        $response->assertJsonPath('approval.letter_ref', 'LTR-001');
        $this->assertStringContainsString('/admin/cases/' . $case->id . '/quote', $response->json('quote.print_url'));
    }

    public function test_admin_military_case_detail_always_shows_armed_forces_sovereign(): void
    {
        $company = $this->civilianCompany();
        $rank    = MilitaryRank::create(['name' => 'نقيب', 'rank_code' => 'CAP', 'sort_order' => 1]);
        $patient = Patient::create([
            'patient_code'     => '642291',
            'patient_qr'       => 'QR-642291',
            'name'             => 'عرفه العسكري',
            'phone'            => '01062204741',
            'patient_type'     => Patient::TYPE_MILITARY,
            'military_rank_id' => $rank->id,
            'rank'             => 'نقيب',
            'registered_at'    => now()->toDateString(),
            'status'           => Patient::STATUS_ACTIVE,
        ]);
        $admin = $this->userWithRole('admin');
        $case  = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update([
            'path'          => CaseRecord::PATH_MILITARY,
            'work_order_no' => 'WO-2026-0002',
            'delivered_at'  => now()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/cases/' . $case->id . '/detail');

        $response->assertOk();
        $response->assertJsonPath('is_military', true);
        $response->assertJsonPath('patient.sovereign', Patient::MILITARY_SOVEREIGN_ENTITY);
        $response->assertJsonPath('patient.company', null);
        $response->assertJsonPath('patient.rank', 'نقيب');
    }

    public function test_admin_case_quote_print_returns_print_view(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['quote_no' => 'QT-2026-0100']);

        Quote::create([
            'quote_no'     => 'QT-2026-0100',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_ISSUED,
            'total'        => 500.00,
        ]);

        $this->actingAs($admin)
            ->get('/admin/cases/' . $case->id . '/quote')
            ->assertOk()
            ->assertSee('QT-2026-0100');
    }
}
