<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\CaseRecord;
use App\Models\Payment;
use App\Models\Quote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * الخزنة — تحصيل الدفع النقدي للمرضى على نفقتهم الشخصية (كاش).
 *
 * عند تأكيد استلام المبلغ:
 *   1) حجز فوري للمواد + توليد أمر شغل + تحويل الحالة للمخزن (عبر OperationsService).
 *   2) تسجيل سجل دفعة (Payment) بوسيلة الدفع.
 *   3) تحديث المبلغ المدفوع على الحالة.
 * كل ذلك داخل معاملة واحدة — يفشل بالكامل إن لم يكفِ المخزون.
 */
class CashierPaymentService
{
    public function __construct(
        private readonly OperationsService $operationsService,
    ) {
    }

    /**
     * @param array{method: string, amount?: float|int|string|null, reference?: ?string, notes?: ?string} $data
     */
    public function confirmPayment(CaseRecord $case, array $data): Payment
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

        $amount = isset($data['amount']) && $data['amount'] !== null && $data['amount'] !== ''
            ? round((float) $data['amount'], 2)
            : (float) ($quote?->total ?? $case->quote_total ?? 0);

        if ($amount <= 0) {
            abort(422, 'قيمة المبلغ غير صالحة.');
        }

        $receivedBy = Auth::user()?->name ?? 'الخزنة';

        return DB::transaction(function () use ($case, $quote, $amount, $method, $data, $receivedBy) {
            // 1) حجز المواد + أمر الشغل + تحويل الحالة للمخزن (يفشل إن لم يكفِ المخزون).
            $this->operationsService->finalizeFromCashier($case, "الخزنة — {$receivedBy}");

            // 2) تسجيل الدفعة.
            $payment = Payment::create([
                'payment_no'   => $this->nextPaymentNo(),
                'case_id'      => $case->id,
                'quote_id'     => $quote?->id,
                'patient_id'   => $case->patient_id,
                'patient_name' => $case->patient?->name ?? $quote?->patient_name,
                'amount'       => $amount,
                'method'       => $method,
                'reference'    => $data['reference'] ?? null,
                'received_by'  => $receivedBy,
                'received_at'  => now(),
                'notes'        => $data['notes'] ?? null,
            ]);

            // 3) تحديث المبلغ المدفوع على الحالة.
            CaseRecord::where('id', $case->id)->update(['paid' => $amount]);

            AuditService::log(
                action:      'payment',
                description: "تحصيل دفعة نقدية بالخزنة — {$payment->payment_no} — " . PaymentMethod::labelFor($method),
                tag:         'financial',
                after:       [
                    'payment_no' => $payment->payment_no,
                    'case_id'    => $case->id,
                    'case_no'    => $case->case_no,
                    'amount'     => $amount,
                    'method'     => $method,
                    'received_by'=> $receivedBy,
                ],
            );

            return $payment->fresh();
        });
    }

    private function nextPaymentNo(): string
    {
        $year   = now()->year;
        $prefix = "PAY-{$year}-";

        $last = Payment::where('payment_no', 'like', $prefix . '%')
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
