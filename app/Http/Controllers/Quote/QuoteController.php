<?php

namespace App\Http\Controllers\Quote;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Quote;
use App\Services\QuoteQrService;
use App\Services\QuoteService;
use App\Support\QuotePrintPresenter;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuoteController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly QuoteService $quoteService,
        private readonly QuoteQrService $quoteQrService,
    ) {
    }

    /**
     * قائمة عروض الأسعار — مدني فقط، بعد إصدار مكتب التشغيل (issued / approved).
     */
    public function index(Request $request): JsonResponse
    {
        $quotes = $this->fetchForDashboard(
            Quote::with([
                'caseRecord:id,case_no,stage_key,patient_type,work_order_no,contract_company_id',
                'caseRecord.contractCompany:id,is_contracted,discount_percent',
                'items',
            ])
                ->whereHas('caseRecord', fn ($q) => $q->where('patient_type', Patient::TYPE_CIVILIAN))
                ->whereIn('status', [Quote::STATUS_ISSUED, Quote::STATUS_APPROVED])
                ->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('quote_no', 'like', "%{$s}%")
                      ->orWhere('patient_name', 'like', "%{$s}%")
                      ->orWhere('order_ref', 'like', "%{$s}%");
                }))
                ->orderByDesc('quote_date')
                ->orderByDesc('id')
        );

        return response()->json([
            'data'  => collect($quotes)->map(fn ($q) => $this->formatQuote($q))->values(),
            'total' => $quotes->count(),
        ]);
    }

    /**
     * إصدار العرض للجهة — يُحدَّث إلى issued استعداداً للطباعة والمسح.
     */
    public function issue(Quote $quote): JsonResponse
    {
        $quote = $this->quoteService->markIssued($quote);

        return response()->json([
            'message' => 'تم إصدار العرض بنجاح.',
            'quote'   => $this->formatQuote($quote),
            'print_url' => route('reception.quote.print', $quote),
        ]);
    }

    /**
     * صفحة طباعة العرض — النموذج الرسمي (وزارة الدفاع).
     * ?embed=1 للمعاينة داخل مودال الاستقبال بدون طباعة تلقائية.
     */
    public function print(Request $request, Quote $quote): View
    {
        $quote->load(['items', 'caseRecord.contractCompany']);

        abort_unless(
            $quote->caseRecord?->patient_type === Patient::TYPE_CIVILIAN,
            404
        );

        return view('quotes.print', [
            'quote'      => $quote,
            'printTotals' => \App\Support\QuotePrintPresenter::fromQuote($quote),
            'quoteQrSvg' => $this->quoteQrService->svg($quote->quote_no),
            'embed'      => $request->boolean('embed'),
            'autoPrint'  => ! $request->boolean('embed'),
        ]);
    }

    /**
     * إذن صرف المخازن — النموذج الرسمي (3.jpeg).
     */
    public function printIssueVoucher(Quote $quote): View
    {
        $quote->load(['caseRecord.bom.items', 'caseRecord.patient']);

        $bom = $quote->caseRecord?->bom;
        abort_unless($bom, 404, 'لا توجد BOM مرتبطة بهذا الطلب.');

        return view('prints.issue-voucher', [
            'voucher'   => \App\Support\IssueVoucherPresenter::fromBom($bom),
            'autoPrint' => true,
        ]);
    }

    private function formatQuote(Quote $quote): array
    {
        $printTotals = QuotePrintPresenter::fromQuote($quote);

        return $quote->only([
            'id',
            'quote_no',
            'order_ref',
            'case_id',
            'patient_name',
            'company_name',
            'quote_date',
            'status',
            'status_label',
            'total',
        ]) + [
            'gross_total'   => $printTotals['gross_total'],
            'display_total' => $printTotals['display_total'],
            'discount_percent' => $printTotals['discount_percent'],
            'quote_serial' => $quote->quote_no,
            'quote_serial_label' => Quote::SERIAL_LABEL,
            'items' => $quote->relationLoaded('items')
                ? $quote->items->map->only(['name', 'stock_item_code', 'qty', 'amount'])
                : [],
            'case' => $quote->relationLoaded('caseRecord') && $quote->caseRecord
                ? $quote->caseRecord->only(['id', 'case_no', 'stage_key', 'work_order_no'])
                : null,
            'print_url' => route('reception.quote.print', ['quote' => $quote, 'embed' => 1]),
        ];
    }
}
