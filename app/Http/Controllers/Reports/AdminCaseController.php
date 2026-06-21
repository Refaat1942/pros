<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use App\Services\AdminCaseDetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AdminCaseController extends Controller
{
    public function __construct(private readonly AdminCaseDetailService $detailService)
    {
    }

    /**
     * تفاصيل الحالة — JSON لنافذة العرض في متابعة الحالات.
     */
    public function show(CaseRecord $case): JsonResponse
    {
        return response()->json($this->detailService->build($case));
    }

    /**
     * عرض/طباعة عرض السعر المرتبط بالحالة (مدني).
     */
    public function quotePrint(CaseRecord $case): View
    {
        abort_if($case->patient_type === Patient::TYPE_MILITARY, 404);

        $quote = Quote::query()
            ->with(['items', 'caseRecord'])
            ->where('case_id', $case->id)
            ->when($case->quote_no, fn ($q) => $q->where('quote_no', $case->quote_no))
            ->orderByDesc('id')
            ->firstOrFail();

        return view('quotes.print', compact('quote'));
    }
}
