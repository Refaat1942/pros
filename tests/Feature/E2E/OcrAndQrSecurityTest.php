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

    public function test_ocr_rejects_mismatched_amount_and_freeze_remains(): void
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

        $this->postJson('/reception/ocr/process', [
            'quote_no' => 'QT-OCR-TEST',
            'patient_name' => $patient->name,
            'approved_amount' => 450.00,
            'company_name' => $company->name,
        ])->assertStatus(422)->assertJsonPath('blocked', true);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);
        $this->assertNull($case->work_order_no);
    }

    public function test_delivery_rejects_tampered_qr_payload(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_READY_DELIVERY);
        Bom::create([
            'bom_no' => 'BOM-SEC-01',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'stage' => Bom::STAGE_FINISHED,
            'finished_at' => now(),
        ]);

        $user = $this->userWithRole('reception');
        $this->actingAs($user);

        $this->postJson('/reception/delivery/scan', [
            'scanned_qr' => 'QR-TAMPERED-NOT-VALID',
        ])->assertStatus(422)->assertJsonPath('security', true);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_READY_DELIVERY, $case->stage_key);
    }

    public function test_delivery_rejects_qr_when_bom_not_finished(): void
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

        $this->postJson('/reception/delivery/scan', [
            'scanned_qr' => $patient->patient_qr,
        ])->assertStatus(422);
    }
}
