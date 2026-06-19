<?php

namespace App\Http\Controllers\Pricing;

use App\Enums\PricingRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\PricingRequest;
use App\Services\PricingService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PricingApprovalController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly PricingService $pricingService)
    {
    }

    /**
     * طابور طلبات التسعير المعلقة.
     */
    public function index(Request $request): JsonResponse
    {
        $requests = $this->fetchForDashboard(
            PricingRequest::with(['caseRecord:id,case_no,stage_key'])
                ->where('status_key', PricingRequestStatus::AwaitingAdminApproval->value)
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('request_no', 'like', "%{$s}%")
                      ->orWhere('patient_name', 'like', "%{$s}%")
                      ->orWhere('order_ref', 'like', "%{$s}%");
                }))
                ->when($request->patient_type, fn ($q, $t) => $q->where('patient_type', $t))
                ->orderByDesc('request_date')
                ->orderByDesc('id')
        );

        return response()->json([
            'data'  => collect($requests)->map(fn ($r) => $this->formatSummary($r))->values(),
            'total' => $requests->count(),
        ]);
    }

    /**
     * تفاصيل طلب التسعير مع تفصيل البنود والمبالغ المحسوبة.
     */
    public function show(PricingRequest $pricingRequest): JsonResponse
    {
        $pricingRequest->load([
            'items',
            'caseRecord:id,case_no,order_ref,stage_key,patient_type,company_name',
            'doctor:id,name',
        ]);

        return response()->json($this->formatDetail($pricingRequest));
    }

    /**
     * اعتماد طلب التسعير — مسار مدني أو عسكري.
     */
    public function approve(Request $request, PricingRequest $pricingRequest): RedirectResponse|JsonResponse
    {
        /** @var \App\Models\User $approver */
        $approver = Auth::user();

        $this->pricingService->approve($pricingRequest, $approver);

        if ($request->expectsJson()) {
            $pricingRequest->refresh()->load(['items', 'quote', 'caseRecord']);

            return response()->json([
                'message'         => 'تم اعتماد طلب التسعير بنجاح.',
                'pricing_request' => $this->formatDetail($pricingRequest),
                'quote'           => $pricingRequest->quote,
                'case'            => $pricingRequest->caseRecord?->only([
                    'id', 'case_no', 'stage_key', 'quote_no', 'quote_total', 'total_cost', 'manufacturing_stage',
                ]),
            ]);
        }

        return redirect()
            ->route('admin.pricing')
            ->with('success', "تم اعتماد طلب التسعير {$pricingRequest->request_no} بنجاح.");
    }

    private function formatSummary(PricingRequest $request): array
    {
        return $request->only([
            'id',
            'request_no',
            'order_ref',
            'patient_name',
            'company_name',
            'request_date',
            'items_count',
            'computed_total',
            'patient_type',
            'status_key',
            'step',
            'status_label',
        ]) + [
            'case' => $request->relationLoaded('caseRecord') ? $request->caseRecord : null,
        ];
    }

    private function formatDetail(PricingRequest $request): array
    {
        return $this->formatSummary($request) + [
            'doctor_name' => $request->doctor_name,
            'items'       => $request->relationLoaded('items')
                ? $request->items->map(fn ($item) => $item->only([
                    'id', 'stock_item_code', 'name', 'qty', 'unit_price', 'line_total',
                ]))
                : [],
            'approved_at'         => $request->approved_at?->toIso8601String(),
            'approved_by'         => $request->approved_by,
            'approved_by_user_id' => $request->approved_by_user_id,
        ];
    }
}
