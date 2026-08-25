<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\CreditNote;
use App\Models\Patient;
use App\Models\User;
use App\Support\PatientEntityPresenter;
use Illuminate\Support\Facades\DB;

/**
 * إشعارات الدائن — إنشاء واعتماد ورفض (مسار مدني بعد التسليم).
 */
class CreditNoteService
{
    public function __construct(private readonly ContractDebtService $contractDebtService) {}

    /**
     * إنشاء إشعار دائن معلّق.
     */
    public function create(CaseRecord $case, string $type, float $amount, string $reason): CreditNote
    {
        $this->assertCivilianDelivered($case);

        $paymentAmount = round($amount, 2);

        if ($paymentAmount <= 0) {
            abort(422, 'مبلغ الإشعار يجب أن يكون أكبر من الصفر.');
        }

        // معاملة + قفل صف الحالة يمنع تجاوز الحد الإجمالي عند إنشاء إشعارات متزامنة.
        $note = DB::transaction(function () use ($case, $type, $paymentAmount, $reason) {
            $lockedCase = CaseRecord::lockForUpdate()->findOrFail($case->id);
            $this->assertCivilianDelivered($lockedCase);

            $ceiling = $this->creditCeiling($lockedCase);

            if ($paymentAmount > $ceiling) {
                abort(422, 'مبلغ الإشعار يتجاوز إجمالي العرض.');
            }

            $committed = $this->sumActiveCreditAmounts($lockedCase->id);
            if (round($committed + $paymentAmount, 2) > $ceiling) {
                abort(422, 'مجموع إشعارات الدائن النشطة يتجاوز إجمالي العرض الاقتصادي للحالة.');
            }

            $lockedCase->load('patient:id,name');

            return CreditNote::create([
                'credit_note_no' => $this->nextCreditNoteNo(),
                'case_id' => $lockedCase->id,
                'order_ref' => $lockedCase->order_ref,
                'patient_name' => $lockedCase->patient?->name ?? '—',
                'company_name' => $lockedCase->company_name,
                'type' => $type,
                'amount' => $paymentAmount,
                'original_total' => $ceiling,
                'reason' => $reason,
                'status' => CreditNote::STATUS_PENDING,
            ]);
        });

        AuditService::log(
            action: 'create',
            description: "إنشاء إشعار دائن {$note->credit_note_no}",
            tag: 'financial',
            after: $note->toArray(),
        );

        return $note;
    }

    /**
     * اعتماد إشعار دائن — تخفيض المستحق وتحديث الحالة.
     */
    public function apply(CreditNote $note, User $approver): CreditNote
    {
        return DB::transaction(function () use ($note, $approver) {
            $note = CreditNote::lockForUpdate()->findOrFail($note->id);

            if ($note->status !== CreditNote::STATUS_PENDING) {
                abort(422, 'إشعار الدائن ليس في حالة انتظار.');
            }

            $case = CaseRecord::lockForUpdate()
                ->with('patient')
                ->findOrFail($note->case_id);
            $this->assertCivilianDelivered($case);

            if (! PatientEntityPresenter::postsContractDebt($case)) {
                abort(422, 'إشعار الدائن متاح فقط لحالات جهة تعاقد متعاقدة.');
            }

            $applyAmount = round((float) $note->amount, 2);
            $ceiling = $this->creditCeiling($case);
            $approvedTotal = $this->sumApprovedCreditAmounts($case->id);

            if (round($approvedTotal + $applyAmount, 2) > $ceiling) {
                abort(422, 'تطبيق الإشعار يتجاوز الحد المسموح لإشعارات الدائن على هذه الحالة.');
            }

            $company = ContractCompany::findOrFail($case->contract_company_id);
            $before = $note->only(['status', 'amount']);

            $this->contractDebtService->decreaseDue($company, $applyAmount);

            $case->update([
                'credit_note_no' => $note->credit_note_no,
                'credit_note_amount' => $note->amount,
            ]);

            $note->update([
                'status' => CreditNote::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $approver->name,
                'approved_by_user_id' => $approver->id,
            ]);

            AuditService::log(
                action: 'credit_note',
                description: "تطبيق إشعار دائن {$note->credit_note_no}",
                tag: 'financial',
                before: $before,
                after: $note->fresh()->only(['status', 'amount', 'approved_at']),
            );

            return $note->fresh();
        });
    }

    /**
     * رفض إشعار دائن معلّق.
     */
    public function reject(CreditNote $note, User $approver, ?string $reason = null): CreditNote
    {
        return DB::transaction(function () use ($note, $approver, $reason) {
            $note = CreditNote::lockForUpdate()->findOrFail($note->id);

            if ($note->status !== CreditNote::STATUS_PENDING) {
                abort(422, 'إشعار الدائن ليس في حالة انتظار.');
            }

            $case = CaseRecord::findOrFail($note->case_id);

            if ($case->isMilitary()) {
                abort(422, 'إشعار الدائن غير متاح للمسار العسكري.');
            }

            $before = $note->only(['status']);

            $note->update([
                'status' => CreditNote::STATUS_REJECTED,
                'approved_at' => now(),
                'approved_by' => $approver->name,
                'approved_by_user_id' => $approver->id,
                'reason' => $reason ?? $note->reason,
            ]);

            AuditService::log(
                action: 'reject',
                description: "رفض إشعار دائن {$note->credit_note_no}",
                tag: 'financial',
                before: $before,
                after: $note->fresh()->only(['status']),
            );

            return $note->fresh();
        });
    }

    private function assertCivilianDelivered(CaseRecord $case): void
    {
        if ($case->isMilitary() || $case->patient_type === Patient::TYPE_MILITARY) {
            abort(422, 'إشعار الدائن غير متاح للمسار العسكري.');
        }

        if ($case->stage_key !== CaseRecord::STAGE_DELIVERED) {
            abort(422, 'إشعار الدائن متاح فقط بعد تسليم الحالة.');
        }
    }

    private function nextCreditNoteNo(): string
    {
        $last = CreditNote::lockForUpdate()
            ->orderByDesc('id')
            ->value('credit_note_no');

        $num = $last && preg_match('/CN-(\d+)/', $last, $m)
            ? ((int) $m[1]) + 1
            : 1;

        return sprintf('CN-%04d', $num);
    }

    /**
     * الحد الاقتصادي الأعلى لإشعارات الدائن على الحالة — يطابق original_total المخزَّن عند الإنشاء.
     */
    private function creditCeiling(CaseRecord $case): float
    {
        return round((float) ($case->quote_total ?? 0), 2);
    }

    /**
     * مجموع إشعارات الدائن النشطة (معلّقة + معتمدة) التي تحجز القدرة الاقتصادية.
     */
    private function sumActiveCreditAmounts(int $caseId): float
    {
        return round((float) CreditNote::query()
            ->where('case_id', $caseId)
            ->whereIn('status', [CreditNote::STATUS_PENDING, CreditNote::STATUS_APPROVED])
            ->sum('amount'), 2);
    }

    /**
     * مجموع الإشعارات المعتمدة فقط — يُستخدم عند التطبيق لمنع الازدواج مع المعلّق.
     */
    private function sumApprovedCreditAmounts(int $caseId): float
    {
        return round((float) CreditNote::query()
            ->where('case_id', $caseId)
            ->where('status', CreditNote::STATUS_APPROVED)
            ->sum('amount'), 2);
    }
}
