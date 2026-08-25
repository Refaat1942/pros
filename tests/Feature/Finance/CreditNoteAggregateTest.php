<?php

namespace Tests\Feature\Finance;

use App\Models\CaseRecord;
use App\Models\CreditNote;
use App\Services\CreditNoteService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * P1-02 — aggregate credit-note capacity per civilian case must not exceed quote_total.
 */
class CreditNoteAggregateTest extends TestCase
{
    use ProstheticTestHelper;

    private function deliveredCaseWithQuote(float $quoteTotal): CaseRecord
    {
        $company = $this->civilianCompany('شركة إشعار دائن');
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['quote_total' => $quoteTotal]);
        $company->debt()->first()->update(['due' => $quoteTotal]);

        return $case->fresh();
    }

  // Test A — single note within limit succeeds
    public function test_single_note_within_limit_succeeds(): void
    {
        $case = $this->deliveredCaseWithQuote(50000);
        $service = app(CreditNoteService::class);

        $note = $service->create($case, CreditNote::TYPE_PARTIAL, 20000, 'خصم اتفاقي');

        $this->assertSame(CreditNote::STATUS_PENDING, $note->status);
        $this->assertSame(20000.0, (float) $note->amount);
        $this->assertSame(50000.0, (float) $note->original_total);
    }

    // Test B — single note exceeds limit
    public function test_single_note_exceeding_ceiling_is_rejected(): void
    {
        $case = $this->deliveredCaseWithQuote(50000);
        $service = app(CreditNoteService::class);

        try {
            $service->create($case, CreditNote::TYPE_PARTIAL, 60000, 'خصم زائد');
            $this->fail('Credit note above ceiling must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(0, CreditNote::where('case_id', $case->id)->count());
        $this->assertSame(50000.0, (float) $case->fresh()->contractCompany->debt->due);
    }

    // Test C — aggregate exceeds limit
    public function test_aggregate_exceeding_ceiling_is_rejected(): void
    {
        $case = $this->deliveredCaseWithQuote(50000);
        $service = app(CreditNoteService::class);
        $admin = $this->userWithRole('admin');

        $existing = $service->create($case, CreditNote::TYPE_PARTIAL, 40000, 'إشعار أول');
        $service->apply($existing, $admin);

        try {
            $service->create($case, CreditNote::TYPE_PARTIAL, 20000, 'إشعار يتجاوز الحد');
            $this->fail('Second credit note exceeding aggregate ceiling must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(1, CreditNote::where('case_id', $case->id)->count());
        $this->assertSame(40000.0, $this->sumActiveCredit($case->id));
    }

    // Test D — aggregate exact limit
    public function test_aggregate_exact_ceiling_succeeds(): void
    {
        $case = $this->deliveredCaseWithQuote(50000);
        $service = app(CreditNoteService::class);
        $admin = $this->userWithRole('admin');

        $existing = $service->create($case, CreditNote::TYPE_PARTIAL, 40000, 'إشعار أول');
        $service->apply($existing, $admin);

        $second = $service->create($case, CreditNote::TYPE_PARTIAL, 10000, 'إشعار يكمّل الحد');

        $this->assertSame(CreditNote::STATUS_PENDING, $second->status);
        $this->assertSame(50000.0, $this->sumActiveCredit($case->id));
    }

    // Test E — inactive (rejected) notes do not consume capacity
    public function test_rejected_notes_do_not_consume_capacity(): void
    {
        $case = $this->deliveredCaseWithQuote(50000);
        $service = app(CreditNoteService::class);
        $admin = $this->userWithRole('admin');

        $rejected = $service->create($case, CreditNote::TYPE_PARTIAL, 40000, 'سيتم رفضه');
        $service->reject($rejected, $admin, 'مرفوض');

        $note = $service->create($case, CreditNote::TYPE_PARTIAL, 40000, 'بعد رفض السابق');

        $this->assertSame(CreditNote::STATUS_PENDING, $note->status);
        $this->assertSame(40000.0, $this->sumActiveCredit($case->id));
    }

    public function test_second_apply_is_rejected_when_aggregate_would_exceed_ceiling(): void
    {
        $case = $this->deliveredCaseWithQuote(50000);
        $service = app(CreditNoteService::class);
        $admin = $this->userWithRole('admin');

        // محاكاة بيانات قديمة: إشعاران معلّقان تجاوزا الحد قبل تطبيق الحماية الإجمالية.
        $first = CreditNote::query()->create([
            'credit_note_no' => 'CN-LEG-001',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => 'اختبار',
            'company_name' => $case->company_name,
            'type' => CreditNote::TYPE_PARTIAL,
            'amount' => 40000,
            'original_total' => 50000,
            'reason' => 'أول',
            'status' => CreditNote::STATUS_PENDING,
        ]);
        $second = CreditNote::query()->create([
            'credit_note_no' => 'CN-LEG-002',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => 'اختبار',
            'company_name' => $case->company_name,
            'type' => CreditNote::TYPE_PARTIAL,
            'amount' => 40000,
            'original_total' => 50000,
            'reason' => 'ثاني',
            'status' => CreditNote::STATUS_PENDING,
        ]);

        $service->apply($first, $admin);

        try {
            $service->apply($second, $admin);
            $this->fail('Applying second note beyond ceiling must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(CreditNote::STATUS_PENDING, $second->fresh()->status);
        $this->assertSame(40000.0, $this->sumApprovedCredit($case->id));
        $this->assertSame(10000.0, (float) $case->fresh()->contractCompany->debt->due);
    }

    private function sumActiveCredit(int $caseId): float
    {
        return round((float) CreditNote::query()
            ->where('case_id', $caseId)
            ->whereIn('status', [CreditNote::STATUS_PENDING, CreditNote::STATUS_APPROVED])
            ->sum('amount'), 2);
    }

    private function sumApprovedCredit(int $caseId): float
    {
        return round((float) CreditNote::query()
            ->where('case_id', $caseId)
            ->where('status', CreditNote::STATUS_APPROVED)
            ->sum('amount'), 2);
    }
}
