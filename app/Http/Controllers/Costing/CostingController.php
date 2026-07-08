<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Services\CostingService;
use App\Services\CostingSnapshotService;
use App\Services\PricingService;
use App\Services\StockCategorySchemaService;
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
        private readonly OverheadCostingEngine $overheadCostingEngine,
        private readonly StockCategorySchemaService $categorySchema,
        private readonly CostingSnapshotService $snapshotService,
    ) {}

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
            'data' => collect($cases)->map(fn (CaseRecord $c) => $this->formatSummary($c))->values(),
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
            'pricingRequest.items.stockItem.attributeValues.field',
        ]);

        $pricing = $case->pricingRequest;

        if ($pricing && $case->bom) {
            $this->pricingService->syncItemsFromBom($case, $pricing);
            $this->pricingService->refreshLinePrices($pricing);
            $pricing->refresh()->load(['items.stockItem.attributeValues.field']);
        } elseif ($pricing && (float) $pricing->computed_total <= 0) {
            $this->pricingService->refreshLinePrices($pricing);
            $pricing->refresh()->load(['items.stockItem.attributeValues.field']);
        }

        // إبقاء لقطة سعر البيع متزامنة مع المواد الحالية.
        if ($pricing) {
            $pricing = $this->snapshotService->refresh($pricing);
            $pricing->load(['items.stockItem.attributeValues.field']);
        }

        $canSeeRates = $this->canSeeRates();

        return response()->json([
            'case' => $this->formatSummary($case),
            'pricing' => $pricing ? $this->formatPricingDetail($pricing, $case) : null,
            'costing' => $pricing ? $this->formatCostingBreakdown($pricing, $canSeeRates) : null,
            'bom' => $case->bom ? [
                'id' => $case->bom->id,
                'bom_no' => $case->bom->bom_no,
                'items' => $case->bom->items->map(fn ($i) => $i->only([
                    'stock_item_code', 'name', 'qty', 'source',
                ]))->values(),
            ] : null,
            'can_see_internal' => CaseFinancialSummary::canSeeInternalCost(),
            'can_see_rates' => $canSeeRates,
        ]);
    }

    /**
     * نِسَب/مكوّنات التكاليف وهامش الربح تظهر للأدمن فقط.
     */
    private function canSeeRates(): bool
    {
        return (bool) Auth::user()?->isAdmin();
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
            'case' => $this->formatSummary($case->load(['patient', 'pricingRequest', 'quotes'])),
        ]);
    }

    private function formatSummary(CaseRecord $case): array
    {
        $pricing = $case->relationLoaded('pricingRequest') ? $case->pricingRequest : null;

        return $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'patient_type', 'path',
        ]) + [
            'pathway_label' => $case->isMilitary() ? 'عسكري' : 'مدني',
            'display_entity' => $case->displayEntity(),
            'tech_notes' => $case->resolvedTechNotes(),
            'computed_total' => $pricing ? (float) $pricing->computed_total : null,
            'selling_price' => $pricing ? (float) $pricing->selling_price : null,
            'costing_mode' => $pricing?->costing_mode,
            'internal_total' => CaseFinancialSummary::canSeeInternalCost() && $pricing
                ? (float) $pricing->internal_total
                : null,
            'pricing_request_no' => $pricing?->request_no,
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
        ];
    }

    /**
     * تفصيل التكاليف التلقائي (طرف صناعي 95% + صرف سريع 40%) — الأساس لعرض السعر.
     */
    private function formatCostingBreakdown(PricingRequest $pricing, bool $canSeeRates = true): array
    {
        $b = $this->snapshotService->breakdown($pricing);

        // غير الأدمن: سعر البيع فقط — بدون نِسَب أو مكوّنات أو تكلفة داخلية.
        if (! $canSeeRates) {
            return [
                'selling_price' => $b['selling_price'],
            ];
        }

        return [
            'materials_total' => $b['materials_total'],
            'base_materials' => $b['base_materials'],
            'quick_materials' => $b['quick_materials'],
            'components' => $b['components'],
            'components_total' => $b['components_total'],
            'base_total_cost' => $b['base_total_cost'],
            'base_profit_rate' => $b['base_profit_rate'],
            'base_profit_amount' => $b['base_profit_amount'],
            'base_selling' => $b['base_selling'],
            'quick_profit_rate' => $b['quick_profit_rate'],
            'quick_profit_amount' => $b['quick_profit_amount'],
            'quick_selling' => $b['quick_selling'],
            'total_cost' => $b['total_cost'],
            'profit_rate' => $b['profit_rate'],
            'profit_amount' => $b['profit_amount'],
            'selling_price' => $b['selling_price'],
        ];
    }

    private function formatPricingDetail(PricingRequest $pricing, CaseRecord $case): array
    {
        $canSeeInternal = CaseFinancialSummary::canSeeInternalCost();
        $company = $case->contractCompany ?? $case->patient?->contractCompany;
        $materialsTotal = round((float) $pricing->items->sum('line_total'), 2);
        $breakdown = $this->overheadCostingEngine->calculate($materialsTotal, $company);

        return $pricing->only([
            'id', 'request_no', 'order_ref', 'patient_name', 'company_name',
            'request_date', 'items_count', 'computed_total', 'status_key',
        ]) + [
            'internal_total' => $canSeeInternal ? (float) $pricing->internal_total : null,
            'materials_highest_total' => $materialsTotal,
            'overhead_breakdown' => $canSeeInternal ? $breakdown : [
                'gross_before_discount' => $breakdown['gross_before_discount'],
                'discount_percent' => $breakdown['discount_percent'],
                'discount_amount' => $breakdown['discount_amount'],
                'net_offer_total' => $breakdown['net_offer_total'],
            ],
            'items' => $pricing->relationLoaded('items')
                ? $pricing->items->map(function ($item) {
                    $stockItem = $item->relationLoaded('stockItem') ? $item->stockItem : null;
                    $criteria = $stockItem
                        ? $this->categorySchema->formatCriteriaSummary($stockItem)
                        : '—';

                    return [
                        'stock_item_code' => $item->stock_item_code,
                        'name' => $item->name,
                        'qty' => $item->qty,
                        'criteria' => $criteria,
                        'line_total' => (float) $item->line_total,
                    ];
                })->values()
                : [],
        ];
    }
}
