<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Services\CostingService;
use App\Services\PricingService;
use App\Services\StockPriceService;
use App\Support\CaseFinancialSummary;
use App\Support\OverheadCostingEngine;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * داشبورد التكاليف (الخطوة 5) — قراءة فقط + تأكيد يدوي.
 */
class CostingController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly CostingService $costingService,
        private readonly PricingService $pricingService,
        private readonly StockPriceService $stockPriceService,
        private readonly OverheadCostingEngine $overheadCostingEngine,
    ) {
    }

    /**
     * طابور الحالات بانتظار تأكيد التكاليف.
     */
    public function index(Request $request): JsonResponse
    {
        $cases = $this->fetchForDashboard(
            CaseRecord::inCostCalc()
                ->with([
                    'patient:id,patient_code,name,patient_type',
                    'techOrderSpec:id,case_id,tech_notes',
                    'pricingRequest:id,case_id,request_no,computed_total,internal_total,items_count,status_key',
                ])
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('case_no', 'like', "%{$s}%")
                      ->orWhere('order_ref', 'like', "%{$s}%")
                      ->orWhereHas('patient', fn ($q) => $q->where('name', 'like', "%{$s}%"));
                }))
                ->orderByDesc('updated_at')
        );

        return response()->json([
            'data'  => collect($cases)->map(fn (CaseRecord $c) => $this->formatSummary($c))->values(),
            'total' => $cases->count(),
        ]);
    }

    /**
     * تفاصيل التكلفة المحسوبة — BOM + بنود بالأسعار (read-only).
     */
    public function show(CaseRecord $case): JsonResponse
    {
        abort_unless($case->isInCostCalc(), 422, 'الحالة ليست في مرحلة التكاليف.');

        $case->load([
            'patient:id,patient_code,name,patient_type,company_name,sovereign_entity,rank,contract_company_id',
            'patient.contractCompany:id,name,is_contracted,discount_percent',
            'contractCompany:id,name,is_contracted,discount_percent',
            'techOrderSpec:id,case_id,tech_notes',
            'bom.items',
            'pricingRequest.items',
        ]);

        $pricing = $case->pricingRequest;

        if ($pricing && $case->bom) {
            $this->pricingService->syncItemsFromBom($case, $pricing);
            $this->pricingService->refreshLinePrices($pricing);
            $pricing->refresh()->load('items');
        } elseif ($pricing && (float) $pricing->computed_total <= 0) {
            $this->pricingService->refreshLinePrices($pricing);
            $pricing->refresh()->load('items');
        }

        return response()->json([
            'case'    => $this->formatSummary($case),
            'pricing' => $pricing ? $this->formatPricingDetail($pricing, $case) : null,
            'bom'     => $case->bom ? [
                'id'     => $case->bom->id,
                'bom_no' => $case->bom->bom_no,
                'items'  => $case->bom->items->map(fn ($i) => $i->only([
                    'stock_item_code', 'name', 'qty', 'source',
                ]))->values(),
            ] : null,
            'can_see_internal' => CaseFinancialSummary::canSeeInternalCost(),
        ]);
    }

    /**
     * تأكيد التكاليف وإصدار عرض السعر — تحويل لمكتب التشغيل.
     */
    public function confirm(CaseRecord $case): JsonResponse
    {
        $case = $this->costingService->confirmAndIssueQuote($case, Auth::user()?->name);

        return response()->json([
            'message' => $case->isMilitary()
                ? 'تم تأكيد التكاليف — اعتماد عسكري تلقائي وتحويل للمخزن.'
                : 'تم تأكيد التكاليف  — الحالة في مكتب التشغيل.',
            'case'    => $this->formatSummary($case->load(['patient', 'pricingRequest', 'quotes'])),
        ]);
    }

    private function formatSummary(CaseRecord $case): array
    {
        $pricing = $case->relationLoaded('pricingRequest') ? $case->pricingRequest : null;

        return $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'patient_type', 'path',
        ]) + [
            'pathway_label'  => $case->isMilitary() ? 'عسكري' : 'مدني',
            'display_entity' => $case->displayEntity(),
            'tech_notes'     => $case->resolvedTechNotes(),
            'computed_total' => $pricing ? (float) $pricing->computed_total : null,
            'internal_total' => CaseFinancialSummary::canSeeInternalCost() && $pricing
                ? (float) $pricing->internal_total
                : null,
            'pricing_request_no' => $pricing?->request_no,
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
        ];
    }

    private function formatPricingDetail(PricingRequest $pricing, CaseRecord $case): array
    {
        $canSeeInternal = CaseFinancialSummary::canSeeInternalCost();
        $company = $case->contractCompany ?? $case->patient?->contractCompany;
        $breakdown = $this->overheadCostingEngine->calculate(
            (float) $pricing->internal_total,
            $company,
        );

        return $pricing->only([
            'id', 'request_no', 'order_ref', 'patient_name', 'company_name',
            'request_date', 'items_count', 'computed_total', 'status_key',
        ]) + [
            'internal_total' => $canSeeInternal ? (float) $pricing->internal_total : null,
            'materials_highest_total' => round((float) $pricing->items->sum('line_total'), 2),
            'overhead_breakdown' => $canSeeInternal ? $breakdown : [
                'gross_before_discount' => $breakdown['gross_before_discount'],
                'discount_percent'      => $breakdown['discount_percent'],
                'discount_amount'       => $breakdown['discount_amount'],
                'net_offer_total'       => $breakdown['net_offer_total'],
            ],
            'items' => $pricing->relationLoaded('items')
                ? $pricing->items->map(function ($item) use ($canSeeInternal) {
                    $wacUnit = $canSeeInternal
                        ? $this->stockPriceService->wacUnitPrice($item->stock_item_code ?? '')
                        : null;

                    return [
                        'stock_item_code' => $item->stock_item_code,
                        'name'            => $item->name,
                        'qty'             => $item->qty,
                        'unit_price'      => (float) $item->unit_price,
                        'line_total'      => (float) $item->line_total,
                        'wac_unit'        => $wacUnit,
                        'wac_line_total'  => $wacUnit !== null
                            ? round($item->qty * $wacUnit, 2)
                            : null,
                    ];
                })->values()
                : [],
        ];
    }
}
