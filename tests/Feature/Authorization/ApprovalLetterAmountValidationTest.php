<?php

namespace Tests\Feature\Authorization;

use App\Models\ApprovalContract;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Quote;
use App\Support\QuotePrintPresenter;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * P2-AUTH-01 — المبلغ المعتمد في خطاب الموافقة يطابق display_total المطبوع (بدون OCR).
 */
class ApprovalLetterAmountValidationTest extends TestCase
{
    use ProstheticTestHelper;

    private function issuedQuoteWithoutDiscount(float $total = 500.00): array
    {
        $this->stockItem('RM-001', qty: 5);
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);
        $case->update(['quote_total' => $total, 'company_name' => $company->name]);

        $quote = Quote::create([
            'quote_no' => 'QT-AMT-'.uniqid(),
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'total' => $total,
        ]);

        return compact('company', 'patient', 'case', 'quote');
    }

    private function confirmPayload(array $ctx, float $amount, array $extra = []): array
    {
        return array_merge([
            'quote_no' => $ctx['quote']->quote_no,
            'patient_name' => $ctx['patient']->name,
            'approved_amount' => $amount,
            'company_name' => $ctx['company']->name,
        ], $extra);
    }

    private function approvalLetterAuditsCount(): int
    {
        return AuditLog::query()
            ->where('action', 'approval_letter')
            ->where('tag', 'quotes')
            ->count();
    }

    public function test_exact_amount_succeeds(): void
    {
        $ctx = $this->issuedQuoteWithoutDiscount(500.00);
        $this->actingAs($this->userWithRole('reception'));

        $this->postJson('/reception/approval-letter/confirm', $this->confirmPayload($ctx, 500.00))
            ->assertOk();

        $this->assertSame(Quote::STATUS_APPROVED, $ctx['quote']->fresh()->status);
        $this->assertDatabaseHas('approval_contracts', [
            'quote_id' => $ctx['quote']->id,
            'approved_amount' => 500.00,
        ]);
    }

    public function test_lower_amount_rejected(): void
    {
        $ctx = $this->issuedQuoteWithoutDiscount(500.00);
        $auditsBefore = $this->approvalLetterAuditsCount();
        $this->actingAs($this->userWithRole('reception'));

        $this->postJson('/reception/approval-letter/confirm', $this->confirmPayload($ctx, 450.00))
            ->assertStatus(422);

        $this->assertSame(Quote::STATUS_ISSUED, $ctx['quote']->fresh()->status);
        $this->assertSame(0, ApprovalContract::where('quote_id', $ctx['quote']->id)->count());
        $this->assertSame($auditsBefore, $this->approvalLetterAuditsCount());
    }

    public function test_higher_amount_rejected(): void
    {
        $ctx = $this->issuedQuoteWithoutDiscount(500.00);
        $auditsBefore = $this->approvalLetterAuditsCount();
        $this->actingAs($this->userWithRole('reception'));

        $this->postJson('/reception/approval-letter/confirm', $this->confirmPayload($ctx, 550.00))
            ->assertStatus(422);

        $this->assertSame(Quote::STATUS_ISSUED, $ctx['quote']->fresh()->status);
        $this->assertSame(0, ApprovalContract::where('quote_id', $ctx['quote']->id)->count());
        $this->assertSame($auditsBefore, $this->approvalLetterAuditsCount());
    }

    public function test_contract_discount_exact_net_amount_succeeds(): void
    {
        $company = $this->civilianCompany('جهة خصم 10%');
        $company->update(['discount_percent' => 10]);

        $this->stockItem('RM-001', qty: 5);
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);
        $case->update([
            'contract_company_id' => $company->id,
            'quote_total' => 500.00,
            'company_name' => $company->name,
        ]);

        $quote = Quote::create([
            'quote_no' => 'QT-DISC-'.uniqid(),
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'total' => 500.00,
        ]);

        $ctx = compact('company', 'patient', 'case', 'quote');
        $canonical = QuotePrintPresenter::approvedAmount($quote);
        $this->assertSame(450.0, $canonical);

        $this->actingAs($this->userWithRole('reception'));

        $this->postJson('/reception/approval-letter/confirm', $this->confirmPayload($ctx, 450.00))
            ->assertOk();

        $this->assertSame(Quote::STATUS_APPROVED, $quote->fresh()->status);
        $this->assertDatabaseHas('approval_contracts', [
            'quote_id' => $quote->id,
            'approved_amount' => 450.00,
        ]);
    }

    public function test_contract_discount_gross_amount_rejected(): void
    {
        $company = $this->civilianCompany('جهة خصم 10% ب');
        $company->update(['discount_percent' => 10]);

        $this->stockItem('RM-001', qty: 5);
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);
        $case->update([
            'contract_company_id' => $company->id,
            'quote_total' => 500.00,
            'company_name' => $company->name,
        ]);

        $quote = Quote::create([
            'quote_no' => 'QT-DISC-G-'.uniqid(),
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'total' => 500.00,
        ]);

        $ctx = compact('company', 'patient', 'case', 'quote');
        $auditsBefore = $this->approvalLetterAuditsCount();
        $this->actingAs($this->userWithRole('reception'));

        $this->postJson('/reception/approval-letter/confirm', $this->confirmPayload($ctx, 500.00))
            ->assertStatus(422);

        $this->assertSame(Quote::STATUS_ISSUED, $quote->fresh()->status);
        $this->assertSame(0, ApprovalContract::where('quote_id', $quote->id)->count());
        $this->assertSame($auditsBefore, $this->approvalLetterAuditsCount());
    }

    public function test_decimal_normalization_accepts_rounded_canonical_amount(): void
    {
        $company = $this->civilianCompany('جهة خصم عشري');
        $company->update(['discount_percent' => 10]);

        $this->stockItem('RM-001', qty: 5);
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);
        $case->update([
            'contract_company_id' => $company->id,
            'quote_total' => 500.00,
            'company_name' => $company->name,
        ]);

        $quote = Quote::create([
            'quote_no' => 'QT-DEC-'.uniqid(),
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'total' => 500.00,
        ]);

        $ctx = compact('company', 'patient', 'case', 'quote');
        $this->actingAs($this->userWithRole('reception'));

        $this->postJson('/reception/approval-letter/confirm', $this->confirmPayload($ctx, 450.004))
            ->assertOk();

        $this->assertDatabaseHas('approval_contracts', [
            'quote_id' => $quote->id,
            'approved_amount' => 450.00,
        ]);
    }
}
