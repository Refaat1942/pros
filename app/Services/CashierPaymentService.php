<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\Payment;
use App\Models\Quote;
use App\Support\ContractBillingSplit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * الخزنة — تحصيل الدفع النقدي للمرضى على نفقتهم الشخصية (كاش).
 *
 * عند تأكيد استلام المبلغ:
 *   1) تسجيل سجل دفعة (Payment) بوسيلة الدفع.
 *   2) تحديث المبلغ المدفوع على الحالة (يدعم الدفع الجزئي).
 *   3) عند اكتمال المبلغ: وسم عرض السعر «مدفوع» وإعادة الحالة لمكتب التشغيل.
 * الحجز الفوري وأمر الشغل يصدران لاحقاً باعتماد مكتب التشغيل.
 */
class CashierPaymentService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly QuoteService $quoteService,
    ) {}

    /**
     * @param  array{method: string, amount?: float|int|string|null, reference?: ?string, notes?: ?string}  $data
     * @return array{payment: Payment, fully_paid: bool, paid_total: float, remaining: float}
     */
    public function confirmPayment(CaseRecord $case, array $data): array
    {
        $case = CaseRecord::findOrFail($case->id);

        if (! $case->isAwaitingCashier()) {
            abort(422, 'الحالة ليست بانتظار الدفع في الخزنة.');
        }

        $method = $data['method'] ?? null;
        if (! in_array($method, PaymentMethod::values(), true)) {
            abort(422, 'وسيلة دفع غير صالحة.');
        }

        $case->loadMissing('patient:id,name');
        $quote = Quote::where('case_id', $case->id)->orderByDesc('id')->first();

        $quoteTotal = round((float) ($quote?->total ?? $case->quote_total ?? 0), 2);
        $patientDue = ContractBillingSplit::patientDue($case, $quoteTotal);
        $alreadyPaid = (float) $case->paid;
        $manualDue = $quoteTotal <= 0.009 && $patientDue <= 0.009;
        $remainingBefore = $manualDue
            ? 0.0
            : max(0, round($patientDue - $alreadyPaid, 2));

        $amount = isset($data['amount']) && $data['amount'] !== null && $data['amount'] !== ''
            ? round((float) $data['amount'], 2)
            : ($manualDue ? 0.0 : $remainingBefore);

        if ($amount <= 0) {
            abort(422, 'قيمة المبلغ غير صالحة.');
        }

        if (! $manualDue && $amount > $remainingBefore + 0.009) {
            abort(422, 'المبلغ يتجاوز المتبقي على المريض ('.number_format($remainingBefore, 2).' ج.م).');
        }

        $receivedBy = Auth::user()?->name ?? 'الخزنة';

        return DB::transaction(function () use ($case, $quote, $amount, $method, $data, $receivedBy, $alreadyPaid, $patientDue, $manualDue) {
            $installmentNo = Payment::query()
                ->where('case_id', $case->id)
                ->count() + 1;

            $payment = Payment::create([
                'payment_no' => $this->nextPaymentNo(),
                'installment_no' => $installmentNo,
                'case_id' => $case->id,
                'quote_id' => $quote?->id,
                'patient_id' => $case->patient_id,
                'patient_name' => $case->patient?->name ?? $quote?->patient_name,
                'amount' => $amount,
                'method' => $method,
                'reference' => $data['reference'] ?? null,
                'received_by' => $receivedBy,
                'received_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $newPaidTotal = round($alreadyPaid + $amount, 2);
            $effectiveDue = $manualDue ? $newPaidTotal : $patientDue;
            $remaining = max(0, round($effectiveDue - $newPaidTotal, 2));
            $fullyPaid = $manualDue || $remaining <= 0.009;

            $caseUpdates = ['paid' => $newPaidTotal];
            if ($manualDue) {
                $caseUpdates['quote_total'] = $newPaidTotal;
                $caseUpdates['total_cost'] = $newPaidTotal;
            }

            CaseRecord::where('id', $case->id)->update($caseUpdates);

            if ($manualDue && $quote) {
                Quote::where('id', $quote->id)->update(['total' => $newPaidTotal]);
            }

            if ($fullyPaid) {
                if ($quote) {
                    $this->quoteService->markPaidAtCashier($quote);
                }

                $this->workflowService->advance($case->fresh(), WorkflowEvent::CashierPaid->value);
            }

            AuditService::log(
                action: 'payment',
                description: $fullyPaid
                    ? "تحصيل دفعة نقدية بالخزنة — {$payment->payment_no} — ".PaymentMethod::labelFor($method)
                    : "تحصيل دفعة جزئية بالخزنة — {$payment->payment_no} — متبقي {$remaining} ج.م",
                tag: 'financial',
                after: [
                    'payment_no' => $payment->payment_no,
                    'installment_no' => $installmentNo,
                    'case_id' => $case->id,
                    'case_no' => $case->case_no,
                    'amount' => $amount,
                    'paid_total' => $newPaidTotal,
                    'remaining' => $remaining,
                    'fully_paid' => $fullyPaid,
                    'method' => $method,
                    'received_by' => $receivedBy,
                    'stage_key' => $fullyPaid ? CaseRecord::STAGE_OPERATIONS : CaseRecord::STAGE_CASHIER,
                ],
            );

            return [
                'payment' => $payment->fresh(),
                'fully_paid' => $fullyPaid,
                'paid_total' => $newPaidTotal,
                'remaining' => $remaining,
            ];
        });
    }

    private function nextPaymentNo(): string
    {
        $year = now()->year;
        $prefix = "PAY-{$year}-";

        $last = Payment::where('payment_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('payment_no')
            ->value('payment_no');

        $num = $last
            ? ((int) substr($last, strlen($prefix)) + 1)
            : 1;

        do {
            $paymentNo = sprintf('%s%04d', $prefix, $num++);
        } while (Payment::where('payment_no', $paymentNo)->exists());

        return $paymentNo;
    }
}
