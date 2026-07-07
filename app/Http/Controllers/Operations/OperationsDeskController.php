<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use App\Services\OperationsService;
use App\Services\QuoteService;
use App\Support\CaseFinancialSummary;
use App\Support\QuotePrintPresenter;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * مكتب التشغيل (الخطوة 7) — مركز القرار: اعتماد عروض الأسعار/الموافقات أو الإعادة.
 */
class OperationsDeskController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly OperationsService $operationsService,
        private readonly QuoteService $quoteService,
    ) {}

    /**
     * عروض الأسعار المُصدَرة للاستقبال/العميل — بانتظار موافقة الجهة (OCR).
     */
    public function quotesAwaitingApproval(Request $request): JsonResponse
    {
        $quotes = $this->fetchForDashboard(
            Quote::with([
                'caseRecord:id,case_no,order_ref,stage_key,manufacturing_stage,work_order_no,patient_type,company_name,approval_date,contract_company_id',
                'caseRecord.patient:id,patient_code,name',
                'caseRecord.contractCompany:id,name,discount_percent,is_contracted',
            ])
                ->where('status', Quote::STATUS_ISSUED)
                ->whereHas('caseRecord', fn ($q) => $q->where('patient_type', Patient::TYPE_CIVILIAN))
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('quote_no', 'like', "%{$s}%")
                        ->orWhere('order_ref', 'like', "%{$s}%")
                        ->orWhere('patient_name', 'like', "%{$s}%")
                        ->orWhere('company_name', 'like', "%{$s}%")
                        ->orWhereHas('caseRecord', fn ($q) => $q->where('case_no', 'like', "%{$s}%"));
                }))
                ->orderByDesc('quote_date')
                ->orderByDesc('id')
        );

        return response()->json([
            'data' => collect($quotes)->map(fn (Quote $q) => $this->formatIssuedQuote($q))->values(),
            'total' => $quotes->count(),
        ]);
    }

    /**
     * طابور القرار — الحالات في مكتب التشغيل بانتظار الاعتماد/الإعادة.
     */
    public function pending(Request $request): JsonResponse
    {
        $cases = $this->fetchForDashboard(
            CaseRecord::atOperations()
                ->with([
                    'patient:id,patient_code,name,patient_type',
                    'contractCompany:id,name,discount_percent,is_contracted',
                    'techOrderSpec:id,case_id,tech_notes',
                    'bom:id,case_id,bom_no,stage',
                    'bom.items:id,bom_id,stock_item_code,name,source,qty',
                    'quotes:id,case_id,quote_no,total,status',
                    'pricingRequest:id,case_id,request_no,computed_total,internal_total',
                ])
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('case_no', 'like', "%{$s}%")
                        ->orWhere('order_ref', 'like', "%{$s}%")
                        ->orWhereHas('patient', fn ($q) => $q->where('name', 'like', "%{$s}%"));
                }))
                ->orderByDesc('updated_at')
        );

        return response()->json([
            'data' => collect($cases)->map(fn (CaseRecord $c) => $this->formatPending($c))->values(),
            'total' => $cases->count(),
        ]);
    }

    /**
     * إصدار عرض السعر للاستقبال — يظهر في قسم عروض الأسعار بعد الموافقة الداخلية.
     */
    public function releaseQuote(CaseRecord $case): JsonResponse
    {
        if ($case->stage_key !== CaseRecord::STAGE_OPERATIONS) {
            abort(422, 'الحالة ليست في مكتب التشغيل — لا يمكن إصدار العرض.');
        }

        if ($case->patient_type === Patient::TYPE_MILITARY) {
            abort(422, 'المسار العسكري لا يتطلب إصدار عرض سعر للاستقبال.');
        }

        $quote = Quote::where('case_id', $case->id)->orderByDesc('id')->firstOrFail();

        // مسار الكاش: المريض على نفقته الشخصية (بلا جهة تعاقد) ⬅️ تحويل تلقائي للخزنة.
        if ($case->isCashCivilian()) {
            $case = $this->operationsService->sendToCashier($case, $quote);
            $quote = $quote->fresh();

            return response()->json([
                'message' => 'تم إصدار عرض السعر — حُوّلت الحالة للخزنة لتحصيل الدفع النقدي.',
                'quote' => [
                    'id' => $quote->id,
                    'quote_no' => $quote->quote_no,
                    'status' => $quote->status,
                    'status_label' => $quote->status_label,
                ],
                'case' => $case->only(['id', 'case_no', 'stage_key', 'manufacturing_stage', 'work_order_no']),
            ]);
        }

        $quote = $this->quoteService->releaseToReception($quote);
        $case = $case->fresh();

        return response()->json([
            'message' => 'تم إصدار عرض السعر للاستقبال — بانتظار رجوع العميل بخطاب الموافقة.',
            'quote' => [
                'id' => $quote->id,
                'quote_no' => $quote->quote_no,
                'status' => $quote->status,
                'status_label' => $quote->status_label,
            ],
            'case' => $case->only(['id', 'case_no', 'stage_key', 'manufacturing_stage', 'work_order_no']),
        ]);
    }

    /**
     * اعتماد الحالة — حجز فوري للمواد + أمر شغل + تحويل للمخزن.
     */
    public function approve(CaseRecord $case): JsonResponse
    {
        $case = $this->operationsService->approve($case, Auth::user()?->name);

        return response()->json([
            'message' => 'تم الاعتماد — حُجزت المواد وحُوّلت الحالة للمخزن.',
            'case' => $case->only(['id', 'case_no', 'stage_key', 'manufacturing_stage', 'work_order_no']),
        ]);
    }

    /**
     * رفض/طلب تعديل — إعادة للمعدلات أو للتوصيف.
     */
    public function returnForRework(Request $request, CaseRecord $case): JsonResponse
    {
        $validated = $request->validate([
            'target' => ['required', 'string', Rule::in([
                CaseRecord::STAGE_ADJUSTMENTS,
                CaseRecord::STAGE_TECHNICAL,
            ])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $case = $this->operationsService->returnForRework(
            $case,
            $validated['target'],
            $validated['reason'] ?? null,
        );

        return response()->json([
            'message' => 'تمت إعادة الحالة للتعديل.',
            'case' => $case->only(['id', 'case_no', 'stage_key']),
        ]);
    }

    private function formatIssuedQuote(Quote $quote): array
    {
        $case = $quote->relationLoaded('caseRecord') ? $quote->caseRecord : null;
        $printTotals = QuotePrintPresenter::fromQuote($quote);

        return $quote->only([
            'id', 'quote_no', 'order_ref', 'case_id', 'patient_name', 'company_name',
            'quote_date', 'status', 'status_label', 'total',
        ]) + [
            'gross_total' => $printTotals['gross_total'],
            'display_total' => $printTotals['display_total'],
            'discount_percent' => $printTotals['discount_percent'],
            'has_discount' => $printTotals['has_discount'],
            'quote_serial' => $quote->quote_no,
            'quote_serial_label' => Quote::SERIAL_LABEL,
            'issued_at' => $quote->updated_at?->toIso8601String(),
            'print_url' => route('operations.quote.print', $quote),
            'stage_label' => $this->issuedQuoteStageLabel($case),
            'case' => $case ? $case->only([
                'id', 'case_no', 'order_ref', 'stage_key', 'manufacturing_stage', 'work_order_no',
            ]) : null,
            'patient' => $case && $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
        ];
    }

    private function issuedQuoteStageLabel(?CaseRecord $case): string
    {
        if (! $case) {
            return '—';
        }

        if ($case->stage_key === CaseRecord::STAGE_MANUFACTURING
            && $case->manufacturing_stage === CaseRecord::MFG_WAREHOUSE) {
            return 'بانتظار موافقة الجهة';
        }

        if ($case->stage_key === CaseRecord::STAGE_OPERATIONS) {
            return 'بانتظار موافقة الجهة';
        }

        return $case->stage_key;
    }

    private function formatPending(CaseRecord $case): array
    {
        $quote = $case->relationLoaded('quotes') ? $case->quotes->sortByDesc('id')->first() : null;
        $printTotals = $quote ? QuotePrintPresenter::fromQuote($quote) : null;
        $displayTotal = $printTotals['display_total'] ?? (float) $case->quote_total;

        return $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'patient_type', 'path', 'quote_no',
        ]) + [
            'pathway_label' => $case->isMilitary() ? 'عسكري' : 'مدني',
            'is_cash' => $case->isCashCivilian(),
            'display_entity' => $case->displayEntity(),
            'tech_notes' => $case->resolvedTechNotes(),
            'quote_total' => $displayTotal,
            'display_quote_total' => $displayTotal,
            'quote' => $quote ? [
                'id' => $quote->id,
                'quote_no' => $quote->quote_no,
                'total' => (float) $quote->total,
                'display_total' => $printTotals['display_total'],
                'gross_total' => $printTotals['gross_total'],
                'discount_percent' => $printTotals['discount_percent'],
                'has_discount' => $printTotals['has_discount'],
                'status' => $quote->status,
                'print_url' => route('operations.quote.print', $quote),
            ] : null,
            // التكلفة الداخلية (WAC) للأدمن فقط — لقياس الربح.
            'internal_cost' => CaseFinancialSummary::canSeeInternalCost()
                ? (float) $case->internal_cost
                : null,
            // الربحية العسكرية — للسوبر أدمن فقط (مجرّدة من واجهة بقية الموظفين).
            'military_selling_price' => $case->isMilitary() && CaseFinancialSummary::canSeeMilitaryProfit()
                ? (float) $case->military_selling_price
                : null,
            'military_markup_pct' => $case->isMilitary() && CaseFinancialSummary::canSeeMilitaryProfit()
                ? (float) $case->military_markup_pct
                : null,
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
            'bom' => $case->relationLoaded('bom') && $case->bom
                ? [
                    'id' => $case->bom->id,
                    'bom_no' => $case->bom->bom_no,
                    'items_count' => $case->bom->relationLoaded('items') ? $case->bom->items->count() : 0,
                ]
                : null,
        ];
    }
}
