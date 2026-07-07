<?php

namespace Tests\Feature\Finance;

use App\Models\CaseRecord;
use App\Models\CreditNote;
use App\Services\ContractDebtService;
use App\Services\CreditNoteService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Feature — Financial posting, debt management, credit notes
 * (الفصل السادس: الإغلاق المالي للحالة)
 *
 * Civilian only — military follows sovereign debt path, tested in MilitaryPipelineTest.
 */
class DebtAndCreditNoteTest extends TestCase
{
    use ProstheticTestHelper;

    // ── ContractDebtService ───────────────────────────────────────────────────

    public function test_increase_due_adds_to_company_debt(): void
    {
        $company = $this->civilianCompany('شركة أ');
        $debt = $company->debt()->first();

        app(ContractDebtService::class)->increaseDue($company, 1500.00);

        $debt->refresh();
        $this->assertEquals(1500.00, (float) $debt->due);
    }

    public function test_record_payment_increases_collected_but_not_due(): void
    {
        $company = $this->civilianCompany('شركة ب');
        $debt = $company->debt()->first();
        $debt->update(['due' => 2000.00]);

        app(ContractDebtService::class)->recordPayment($company, 500.00);

        $debt->refresh();
        // due does NOT decrease from a payment — it reflects total owed from contract
        $this->assertEquals(2000.00, (float) $debt->due);
        $this->assertEquals(500.00, (float) $debt->collected);
    }

    public function test_record_payment_writes_audit_log(): void
    {
        $company = $this->civilianCompany('شركة د');
        $company->debt()->first()->update(['due' => 1000.00]);

        app(ContractDebtService::class)->recordPayment($company, 300.00);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment',
            'tag' => 'financial',
        ]);
    }

    // ── CreditNoteService ─────────────────────────────────────────────────────

    public function test_credit_note_created_for_civilian_delivered_case(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['quote_total' => 800.00]);
        $company->debt()->first()->update(['due' => 800.00]);

        $cn = app(CreditNoteService::class)->create($case, 'discount', 200.00, 'خصم اتفاقي');

        $this->assertEquals(CreditNote::STATUS_PENDING, $cn->status);
        $this->assertEquals(200.00, (float) $cn->amount);
    }

    public function test_applying_credit_note_reduces_debt(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['quote_total' => 800.00]);
        $company->debt()->first()->update(['due' => 800.00]);

        $admin = $this->userWithRole('admin');

        $cn = app(CreditNoteService::class)->create($case, 'discount', 200.00, 'خصم اتفاقي');
        app(CreditNoteService::class)->apply($cn, $admin);

        $company->debt()->first()->refresh();
        $this->assertEquals(600.00, (float) $company->debt()->first()->due,
            'Applying credit note must reduce company debt by credit amount');
    }

    public function test_credit_note_cannot_exceed_case_total(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['quote_total' => 300.00]);
        $company->debt()->first()->update(['due' => 300.00]);

        $this->expectException(HttpException::class);

        app(CreditNoteService::class)->create($case, 'discount', 999.00, 'خصم زائد');
    }

    public function test_credit_note_rejected_status_is_set(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['quote_total' => 500.00]);
        $company->debt()->first()->update(['due' => 500.00]);

        $admin = $this->userWithRole('admin');

        $cn = app(CreditNoteService::class)->create($case, 'discount', 100.00, 'مطالبة مرفوضة');
        app(CreditNoteService::class)->reject($cn, $admin, 'المبلغ غير مطابق للعقد');

        $this->assertEquals(CreditNote::STATUS_REJECTED, $cn->fresh()->status);
    }

    public function test_rejected_credit_note_does_not_change_debt(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['quote_total' => 500.00]);
        $company->debt()->first()->update(['due' => 500.00]);

        $admin = $this->userWithRole('admin');

        $cn = app(CreditNoteService::class)->create($case, 'discount', 100.00, 'مطالبة مرفوضة');
        app(CreditNoteService::class)->reject($cn, $admin, 'لا يوجد مستند');

        $this->assertEquals(500.00, (float) $company->debt()->first()->fresh()->due,
            'Rejected credit note must not touch the debt balance');
    }

    /** Military cases must never have credit notes */
    public function test_credit_note_blocked_for_military_case(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['total_cost' => 500.00]);

        $this->expectException(HttpException::class);

        app(CreditNoteService::class)->create($case, 'discount', 100.00, 'محاولة مرفوضة للعسكري');
    }
}
