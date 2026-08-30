<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\ConfirmPaymentRequest;
use App\Models\CaseRecord;
use App\Models\Payment;
use App\Services\CashierPaymentService;
use App\Services\Authorization\ResourceAuthorizationService;
use App\Support\ContractBillingSplit;
use App\Support\PaymentReceiptPresenter;
use App\Traits\PaginationTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الخزنة — طابور تحصيل الدفع النقدي لمرضى الكاش وتأكيد استلام المبالغ.
 */
class CashierDeskController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly CashierPaymentService $cashierPaymentService,
        private readonly ResourceAuthorizationService $resourceAuth,
    ) {}

    /**
     * طابور الحالات بانتظار تحصيل الدفع في الخزنة.
     */
    public function queue(Request $request): JsonResponse
    {
        $cases = $this->fetchForDashboard(
            CaseRecord::query()
                ->awaitingCashier()
                ->with([
                    'patient:id,patient_code,name,phone',
                    'quotes:id,case_id,quote_no,total,status',
                ])
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('case_no', 'like', "%{$s}%")
                        ->orWhere('order_ref', 'like', "%{$s}%")
                        ->orWhere('quote_no', 'like', "%{$s}%")
                        ->orWhereHas('patient', fn ($q) => $q->where('name', 'like', "%{$s}%"));
                }))
                ->orderByDesc('updated_at')
        );

        return response()->json([
            'data' => collect($cases)->map(fn (CaseRecord $c) => $this->formatCase($c))->values(),
            'total' => $cases->count(),
        ]);
    }

    /**
     * تأكيد استلام المبلغ — تسجيل الدفعة وإعادة الحالة لمكتب التشغيل لاعتماد أمر الشغل.
     */
    public function confirm(ConfirmPaymentRequest $request, CaseRecord $case): JsonResponse
    {
        $this->resourceAuth->assertCanConfirmCashPayment(auth()->user(), $case);

        $result = $this->cashierPaymentService->confirmPayment($case, $request->validated());
        $payment = $result['payment'];

        $message = $result['fully_paid']
            ? 'تم تأكيد استلام المبلغ بالكامل — أُعيدت الحالة لمكتب التشغيل لاعتماد إصدار أمر الشغل.'
            : 'تم تسجيل دفعة جزئية — المتبقي '.number_format($result['remaining'], 2).' ج.م على المريض.';

        return response()->json([
            'message' => $message,
            'fully_paid' => $result['fully_paid'],
            'paid_total' => $result['paid_total'],
            'remaining' => $result['remaining'],
            'payment' => [
                'id' => $payment->id,
                'payment_no' => $payment->payment_no,
                'installment_no' => (int) $payment->installment_no,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'receipt_url' => route('cashier.payments.receipt', $payment),
            ],
        ]);
    }

    /**
     * سجل دفعات حالة — للعرض في نافذة التحصيل.
     */
    public function casePayments(CaseRecord $case): JsonResponse
    {
        $this->resourceAuth->assertCanViewCasePayments(auth()->user(), $case);

        $payments = Payment::query()
            ->where('case_id', $case->id)
            ->orderBy('installment_no')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'payment_no' => $p->payment_no,
                'installment_no' => (int) $p->installment_no,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'method_label' => $p->methodLabel(),
                'received_at' => $p->received_at?->format('d/m/Y H:i'),
                'receipt_url' => route('cashier.payments.receipt', $p),
            ])->values(),
        ]);
    }

    /**
     * إيصال دفع مطبوع (A4) — يظهر رقم الدفعة كسيريال رسمي.
     */
    public function printReceipt(Request $request, Payment $payment): View
    {
        $this->resourceAuth->assertCanViewPayment(auth()->user(), $payment);

        $payment->load(['caseRecord', 'patient']);

        return view('prints.payment-receipt', [
            'receipt' => PaymentReceiptPresenter::fromPayment($payment),
            'autoPrint' => ! $request->boolean('embed'),
        ]);
    }

    private function formatCase(CaseRecord $case): array
    {
        $quote = $case->relationLoaded('quotes') ? $case->quotes->sortByDesc('id')->first() : null;
        $amountDue = ContractBillingSplit::patientDue(
            $case,
            (float) ($quote?->total ?? $case->quote_total ?? 0),
        );
        $paid = (float) $case->paid;
        $remaining = max(0, $amountDue - $paid);

        return $case->only([
            'id', 'case_no', 'order_ref', 'quote_no',
        ]) + [
            'amount' => $amountDue,
            'amount_due' => $amountDue,
            'paid' => $paid,
            'remaining' => $remaining,
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name', 'phone'])
                : null,
            'quote' => $quote ? [
                'id' => $quote->id,
                'quote_no' => $quote->quote_no,
                'total' => (float) $quote->total,
                'print_url' => route('cashier.quote.print', $quote),
            ] : null,
        ];
    }
}
