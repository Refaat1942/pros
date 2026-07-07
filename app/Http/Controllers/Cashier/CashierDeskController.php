<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\ConfirmPaymentRequest;
use App\Models\CaseRecord;
use App\Models\Payment;
use App\Services\CashierPaymentService;
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
        $payment = $this->cashierPaymentService->confirmPayment($case, $request->validated());

        return response()->json([
            'message' => 'تم تأكيد استلام المبلغ — أُعيدت الحالة لمكتب التشغيل لاعتماد إصدار أمر الشغل.',
            'payment' => [
                'id' => $payment->id,
                'payment_no' => $payment->payment_no,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'receipt_url' => route('cashier.payments.receipt', $payment),
            ],
        ]);
    }

    /**
     * إيصال دفع مطبوع (A4) — يظهر رقم الدفعة كسيريال رسمي.
     */
    public function printReceipt(Request $request, Payment $payment): View
    {
        $payment->load(['caseRecord', 'patient']);

        return view('prints.payment-receipt', [
            'receipt' => PaymentReceiptPresenter::fromPayment($payment),
            'autoPrint' => ! $request->boolean('embed'),
        ]);
    }

    private function formatCase(CaseRecord $case): array
    {
        $quote = $case->relationLoaded('quotes') ? $case->quotes->sortByDesc('id')->first() : null;

        return $case->only([
            'id', 'case_no', 'order_ref', 'quote_no',
        ]) + [
            'amount' => (float) ($quote?->total ?? $case->quote_total ?? 0),
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
