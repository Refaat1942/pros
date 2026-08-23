<?php

namespace Tests\Feature\E2E;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Quote;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class OcrAndQrSecurityTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_ocr_accepts_manual_amount_without_quote_match(): void
    {
        $this->stockItem('RM-001', qty: 5);
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);
        $case->update(['quote_total' => 500.00, 'company_name' => $company->name]);

        Quote::create([
            'quote_no' => 'QT-OCR-TEST',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'total' => 500.00,
        ]);

        $user = $this->userWithRole('reception');
        $this->actingAs($user);

        $this->postJson('/reception/approval-letter/confirm', [
            'quote_no' => 'QT-OCR-TEST',
            'patient_name' => $patient->name,
            'approved_amount' => 450.00,
            'company_name' => $company->name,
        ])->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);
        $this->assertEquals(Quote::STATUS_APPROVED, Quote::where('quote_no', 'QT-OCR-TEST')->value('status'));
    }

    public function test_delivery_confirm_blocked_when_bom_not_finished(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_READY_DELIVERY);
        Bom::create([
            'bom_no' => 'BOM-SEC-02',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'stage' => Bom::STAGE_WIP,
        ]);

        $this->actingAs($this->userWithRole('reception'));

        $this->postJson('/reception/delivery/'.$case->id.'/confirm')
            ->assertStatus(422);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_READY_DELIVERY, $case->stage_key);
    }
}
