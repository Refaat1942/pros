<?php

namespace App\Http\Controllers\Reports;

use App\Enums\CaseStage;
use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use App\Services\AdminCaseDetailService;
use App\Services\CaseWorkflowSkipService;
use App\Services\QuoteQrService;
use App\Services\WorkflowPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCaseController extends Controller
{
    public function __construct(
        private readonly AdminCaseDetailService $detailService,
        private readonly QuoteQrService $quoteQrService,
        private readonly CaseWorkflowSkipService $workflowSkip,
        private readonly WorkflowPolicyService $workflowPolicies,
    ) {}

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
    public function quotePrint(Request $request, CaseRecord $case): View
    {
        abort_if($case->patient_type === Patient::TYPE_MILITARY, 404);

        $quote = Quote::query()
            ->with(['items', 'caseRecord'])
            ->where('case_id', $case->id)
            ->when($case->quote_no, fn ($q) => $q->where('quote_no', $case->quote_no))
            ->orderByDesc('id')
            ->firstOrFail();

        $embed = $request->boolean('embed');

        return view('quotes.print', [
            'quote' => $quote,
            'quoteQrSvg' => $this->quoteQrService->svg($quote->quote_no),
            'embed' => $embed,
            'autoPrint' => ! $embed,
        ]);
    }

    /**
     * تخطي مرحلة اختيارية للحالة — للإدارة حسب سياسات التدفق.
     */
    public function skipStage(CaseRecord $case): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $updated = $this->workflowSkip->skipCurrentStage($case, $user);

        return response()->json([
            'message' => 'تم تخطي المرحلة.',
            'case' => [
                'id' => $updated->id,
                'case_no' => $updated->case_no,
                'stage_key' => $updated->stage_key,
                'stage_label' => CaseStage::labelFor($updated->stage_key),
            ],
            'skippable' => $this->workflowPolicies->skippableStageKeys(
                $this->workflowPolicies->pathwayForCase($updated),
            ),
        ]);
    }
}
