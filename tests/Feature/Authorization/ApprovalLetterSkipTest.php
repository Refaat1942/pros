<?php

namespace Tests\Feature\Authorization;

use App\Models\ApprovalContract;
use App\Models\CaseRecord;
use App\Models\Quote;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ApprovalLetterSkipTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_approval_defaults_without_upload_enables_manual_confirm(): void
    {
        $this->stockItem('RM-001', qty: 5);
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $quote = Quote::create([
            'quote_no' => 'QT-SKIP-'.uniqid(),
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'total' => 500.00,
        ]);

        $reception = $this->userWithRole('reception');

        $this->actingAs($reception)
            ->getJson('/reception/approval-letter/defaults?quote_no='.$quote->quote_no)
            ->assertOk()
            ->assertJsonPath('defaults.patient_name', $patient->name)
            ->assertJsonPath('defaults.approved_amount', 500);

        $this->actingAs($reception)
            ->postJson('/reception/approval-letter/confirm', [
                'quote_no' => $quote->quote_no,
                'patient_name' => $patient->name,
                'approved_amount' => 500.00,
                'company_name' => $company->name,
                'letter_path' => null,
            ])
            ->assertOk();

        $this->assertSame(Quote::STATUS_APPROVED, $quote->fresh()->status);
        $this->assertDatabaseHas('approval_contracts', [
            'quote_id' => $quote->id,
            'approved_amount' => 500.00,
            'letter_path' => null,
        ]);
        $this->assertSame(0, ApprovalContract::where('quote_id', $quote->id)->whereNotNull('letter_path')->count());
    }

    public function test_confirm_is_idempotent_when_quote_already_approved(): void
    {
        $this->stockItem('RM-001', qty: 5);
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $quote = Quote::create([
            'quote_no' => 'QT-SKIP-'.uniqid(),
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'total' => 500.00,
        ]);

        $reception = $this->userWithRole('reception');
        $payload = [
            'quote_no' => $quote->quote_no,
            'patient_name' => $patient->name,
            'approved_amount' => 500.00,
            'company_name' => $company->name,
            'letter_path' => null,
        ];

        $this->actingAs($reception)
            ->postJson('/reception/approval-letter/confirm', $payload)
            ->assertOk();

        $this->actingAs($reception)
            ->postJson('/reception/approval-letter/confirm', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'تم اعتماد هذا العرض مسبقاً — بانتظار إصدار أمر الشغل من مكتب التشغيل.');

        $this->assertSame(Quote::STATUS_APPROVED, $quote->fresh()->status);
        $this->assertSame(1, ApprovalContract::where('quote_id', $quote->id)->count());
    }
}
