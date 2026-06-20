<?php

namespace App\Http\Controllers\Pricing;

use App\Enums\PricingRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\PricingRequest;
use App\Services\PricingService;
use App\Support\CaseDisplayStatus;
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
            PricingRequest::with(['caseRecord:id,case_no,stage_key,manufacturing_stage'])
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
        if ($this->needsPriceRefresh($pricingRequest)) {
            $this->pricingService->refreshLinePrices($pricingRequest);
            $pricingRequest->refresh();
        }

        $pricingRequest->load([
            'items',
            'caseRecord.patient:id,patient_code,name,phone,national_id,patient_type,rank,sovereign_entity,company_name',
            'caseRecord:id,case_no,order_ref,stage_key,patient_type,company_name,patient_id,work_order_no,manufacturing_stage',
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
        $display = CaseDisplayStatus::forPricingRequest($request);

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
            'display_status_label',
            'display_status_badge_class',
        ]) + [
            'display_status' => $display->toArray(),
            'case' => $request->relationLoaded('caseRecord') ? $request->caseRecord : null,
        ];
    }

    private function formatDetail(PricingRequest $request): array
    {
        $case = $request->relationLoaded('caseRecord') && $request->caseRecord
            ? $request->caseRecord
            : null;
        $patient = $case && $case->relationLoaded('patient') && $case->patient
            ? $case->patient
            : null;

        return $this->formatSummary($request) + [
            'doctor_name' => $request->doctor_name,
            'patient'     => $patient ? [
                'patient_code'     => $patient->patient_code,
                'name'             => $patient->name,
                'phone'            => $patient->phone,
                'national_id'      => $patient->national_id,
                'patient_type'     => $patient->patient_type,
                'rank'             => $patient->rank,
                'sovereign_entity' => $patient->sovereign_entity,
                'company_name'     => $patient->company_name,
            ] : null,
            'case' => $case ? [
                'id'            => $case->id,
                'case_no'       => $case->case_no,
                'order_ref'     => $case->order_ref,
                'stage_key'     => $case->stage_key,
                'patient_type'  => $case->patient_type,
                'company_name'  => $case->company_name,
                'work_order_no' => $case->work_order_no,
            ] : null,
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

    private function needsPriceRefresh(PricingRequest $request): bool
    {
        if ((float) $request->computed_total > 0) {
            return false;
        }

        return $request->items()
            ->where(function ($q) {
                $q->whereNull('unit_price')
                    ->orWhere('unit_price', '<=', 0);
            })
            ->exists();
    }
}
