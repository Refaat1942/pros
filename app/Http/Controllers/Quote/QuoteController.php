<?php

namespace App\Http\Controllers\Quote;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Quote;
use App\Services\QuoteService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuoteController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly QuoteService $quoteService)
    {
    }

    /**
     * قائمة عروض الأسعار — مدني فقط (pending / issued / approved).
     */
    public function index(Request $request): JsonResponse
    {
        $quotes = $this->fetchForDashboard(
            Quote::with([
                'caseRecord:id,case_no,stage_key,patient_type,work_order_no',
                'items',
            ])
                ->whereHas('caseRecord', fn ($q) => $q->where('patient_type', Patient::TYPE_CIVILIAN))
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
     * صفحة طباعة العرض مع QR يُرمِز quote_no.
     */
    public function print(Quote $quote): View
    {
        $quote->load(['items', 'caseRecord']);

        abort_unless(
            $quote->caseRecord?->patient_type === Patient::TYPE_CIVILIAN,
            404
        );

        return view('quotes.print', compact('quote'));
    }

    private function formatQuote(Quote $quote): array
    {
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
            'items' => $quote->relationLoaded('items')
                ? $quote->items->map->only(['name', 'stock_item_code', 'qty', 'amount'])
                : [],
            'case' => $quote->relationLoaded('caseRecord') && $quote->caseRecord
                ? $quote->caseRecord->only(['id', 'case_no', 'stage_key', 'work_order_no'])
                : null,
        ];
    }
}
