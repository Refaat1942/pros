<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\CreditNote;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * إشعارات الدائن — إنشاء واعتماد ورفض (مسار مدني بعد التسليم).
 */
class CreditNoteService
{
    public function __construct(private readonly ContractDebtService $contractDebtService)
    {
    }

    /**
     * إنشاء إشعار دائن معلّق.
     */
    public function create(CaseRecord $case, string $type, float $amount, string $reason): CreditNote
    {
        $this->assertCivilianDelivered($case);

        if ($amount <= 0) {
            abort(422, 'مبلغ الإشعار يجب أن يكون أكبر من الصفر.');
        }

        $originalTotal = (float) ($case->quote_total ?? 0);

        if ($amount > $originalTotal) {
            abort(422, 'مبلغ الإشعار يتجاوز إجمالي العرض.');
        }

        $case->load('patient:id,name');

        $note = CreditNote::create([
            'credit_note_no' => $this->nextCreditNoteNo(),
            'case_id'        => $case->id,
            'order_ref'      => $case->order_ref,
            'patient_name'   => $case->patient?->name ?? '—',
            'company_name'   => $case->company_name,
            'type'           => $type,
            'amount'         => $amount,
            'original_total' => $originalTotal,
            'reason'         => $reason,
            'status'         => CreditNote::STATUS_PENDING,
        ]);

        AuditService::log(
            action:      'create',
            description: "إنشاء إشعار دائن {$note->credit_note_no}",
            tag:         'financial',
            after:       $note->toArray(),
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

            $case = CaseRecord::with('patient')->findOrFail($note->case_id);
            $this->assertCivilianDelivered($case);

            if (! $case->contract_company_id) {
                abort(422, 'الحالة غير مرتبطة بجهة تعاقد.');
            }

            $company = ContractCompany::findOrFail($case->contract_company_id);
            $before  = $note->only(['status', 'amount']);

            $this->contractDebtService->decreaseDue($company, (float) $note->amount);

            $case->update([
                'credit_note_no'     => $note->credit_note_no,
                'credit_note_amount' => $note->amount,
            ]);

            $note->update([
                'status'               => CreditNote::STATUS_APPROVED,
                'approved_at'          => now(),
                'approved_by'          => $approver->name,
                'approved_by_user_id'  => $approver->id,
            ]);

            AuditService::log(
                action:      'credit_note',
                description: "تطبيق إشعار دائن {$note->credit_note_no}",
                tag:         'financial',
                before:      $before,
                after:       $note->fresh()->only(['status', 'amount', 'approved_at']),
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
                'status'              => CreditNote::STATUS_REJECTED,
                'approved_at'         => now(),
                'approved_by'         => $approver->name,
                'approved_by_user_id' => $approver->id,
                'reason'              => $reason ?? $note->reason,
            ]);

            AuditService::log(
                action:      'reject',
                description: "رفض إشعار دائن {$note->credit_note_no}",
                tag:         'financial',
                before:      $before,
                after:       $note->fresh()->only(['status']),
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
}
