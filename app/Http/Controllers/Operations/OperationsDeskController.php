<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Services\OperationsService;
use App\Support\CaseFinancialSummary;
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

    public function __construct(private readonly OperationsService $operationsService)
    {
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
            'data'  => collect($cases)->map(fn (CaseRecord $c) => $this->formatPending($c))->values(),
            'total' => $cases->count(),
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
            'case'    => $case->only(['id', 'case_no', 'stage_key', 'manufacturing_stage', 'work_order_no']),
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
            'case'    => $case->only(['id', 'case_no', 'stage_key']),
        ]);
    }

    private function formatPending(CaseRecord $case): array
    {
        $quote = $case->relationLoaded('quotes') ? $case->quotes->sortByDesc('id')->first() : null;

        return $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'patient_type', 'path', 'quote_no',
        ]) + [
            'pathway_label'  => $case->isMilitary() ? 'عسكري' : 'مدني',
            'display_entity' => $case->displayEntity(),
            'quote_total'    => (float) $case->quote_total,
            'quote'          => $quote ? [
                'id'        => $quote->id,
                'quote_no'  => $quote->quote_no,
                'total'     => (float) $quote->total,
                'status'    => $quote->status,
                'print_url' => route('operations.quote.print', $quote),
            ] : null,
            // التكلفة الداخلية (WAC) للأدمن فقط — لقياس الربح.
            'internal_cost'  => CaseFinancialSummary::canSeeInternalCost()
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
                    'id'          => $case->bom->id,
                    'bom_no'      => $case->bom->bom_no,
                    'items_count' => $case->bom->relationLoaded('items') ? $case->bom->items->count() : 0,
                ]
                : null,
        ];
    }
}
