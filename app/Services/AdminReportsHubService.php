<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Enums\ManufacturingStage;
use App\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Models\ApprovalContract;
use App\Models\AuditLog;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Models\DebtCollectionEntry;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ReturnNote;
use App\Models\ServicesApproval;
use App\Models\SpecEditRequest;
use App\Models\StockCategory;
use App\Models\StockDispenseRequest;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\WorkshopSection;
use App\Support\CaseFinancialSummary;
use App\Support\ClinicTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * مركز التقارير — بطاقات أقسام لوحة الإدارة مع فلترة تاريخية وتصدير.
 */
class AdminReportsHubService
{
    public function __construct(
        private readonly AdminReportsService $snapshotReports,
        private readonly SupplierService $supplierService,
        private readonly AdminPatientTrackService $patientTracks,
        private readonly FinancialBalanceService $balanceService,
        private readonly ProfitabilityReportService $profitabilityService,
        private readonly InventoryFinancialReconciliationService $reconciliationService,
        private readonly ItemPricingAnalyticsService $itemPricingAnalytics,
    ) {}

    /** @return list<array{id: string, label: string, icon: string, group: string, description: string}> */
    public function sections(): array
    {
        $pages = config('dashboards.admin.pages', []);
        $skip = ['overview', 'bi', 'general-view', 'reports', 'reports-section', 'permissions', 'employees', 'notifications', 'military-ranks', 'military-debts', 'costing-settings', 'branding-settings', 'pathway-settings', 'notification-settings'];

        $cards = [];
        $groups = [
            'patient-tracks' => 'مسار المرضى والحالات',
            'cases' => 'مسار المرضى والحالات',
            'spec-edit-requests' => 'مسار المرضى والحالات',
            'visit-types' => 'مسار المرضى والحالات',
            'services-approvals' => 'مسار المرضى والحالات',
            'workshop-sections' => 'الورشة والإنتاج',
            'workshop-tracking' => 'الورشة والإنتاج',
            'catalog' => 'المخزون والتوريد',
            'stock-categories' => 'المخزون والتوريد',
            'inventory-overview' => 'المخزون والتوريد',
            'suppliers' => 'المخزون والتوريد',
            'returns' => 'المخزون والتوريد',
            'dispense-approvals' => 'المخزون والتوريد',
            'companies' => 'التعاقد والمالية',
            'contracts' => 'التعاقد والمالية',
            'civilian-debts' => 'التعاقد والمالية',
            'audit' => 'الرقابة',
            'financial' => 'رؤية عامة',
            'inventory' => 'رؤية عامة',
            'operations' => 'رؤية عامة',
            'bom' => 'رؤية عامة',
        ];

        $reportLabels = [
            'civilian-debts' => 'المديونات',
        ];

        foreach ($pages as $slug => $meta) {
            if (in_array($slug, $skip, true) || ! empty($meta['hidden'])) {
                continue;
            }

            $cards[] = [
                'id' => $slug,
                'label' => $reportLabels[$slug] ?? ($meta['label'] ?? $slug),
                'icon' => $meta['icon'] ?? '📄',
                'group' => $groups[$slug] ?? 'أخرى',
                'description' => $meta['title'] ?? '',
            ];
        }

        foreach ([
            ['id' => 'cash-income', 'label' => 'التحصيل النقدي — الخزنة', 'icon' => '💵', 'group' => 'التعاقد والمالية', 'description' => 'المبالغ النقدية المُحصّلة من الخزنة (كاش / إنستاباي / فودافون كاش)'],
            ['id' => 'financial', 'label' => 'الإيرادات والمالية', 'icon' => '💰', 'group' => 'رؤية عامة', 'description' => 'إيرادات التسليم وأوامر التشغيل'],
            ['id' => 'inventory', 'label' => 'تحليلات المخزون', 'icon' => '📦', 'group' => 'رؤية عامة', 'description' => 'الأصناف الراكدة والشغالة ومنخفضة المخزون'],
            ['id' => 'inventory-valuation', 'label' => 'تقييم المخزون', 'icon' => '💎', 'group' => 'المخزون والتوريد', 'description' => 'رصيد كل صنف وكمياته وأسعاره وقيمته بالمخزن'],
            ['id' => 'item-margins', 'label' => 'هامش الربح بالأصناف', 'icon' => '📊', 'group' => 'المخزون والتوريد', 'description' => 'WAC مقابل أعلى سعر شراء — هامش الوحدة ونسبته لكل صنف'],
            ['id' => 'inventory-reconciliation', 'label' => 'تسوية مخزون ↔ مالية', 'icon' => '🔗', 'group' => 'التعاقد والمالية', 'description' => 'ربط صرف المخزن (WAC) بالإيرادات والتكلفة المُسلَّمة'],
            ['id' => 'operations', 'label' => 'التشغيل والأوامر', 'icon' => '🎯', 'group' => 'رؤية عامة', 'description' => 'أوامر التحضير والورشة'],
            ['id' => 'bom', 'label' => 'قوائم المواد', 'icon' => '📋', 'group' => 'رؤية عامة', 'description' => 'تقييم قوائم المواد حسب أعلى سعر دفعة شراء'],
        ] as $extra) {
            $cards[] = $extra;
        }

        if (Gate::allows('view-costs')) {
            foreach ([
                ['id' => 'opening-balance', 'label' => 'رصيد أول المدة', 'icon' => '🏦', 'group' => 'التعاقد والمالية', 'description' => 'الأرصدة الافتتاحية للخزنة والمديونيات وقيمة المخزون في بداية الفترة'],
                ['id' => 'closing-balance', 'label' => 'رصيد آخر المدة', 'icon' => '🧾', 'group' => 'التعاقد والمالية', 'description' => 'الأرصدة الختامية بعد حركة الفترة للخزنة والمديونيات وقيمة المخزون'],
                ['id' => 'profitability', 'label' => 'مراجعة التكاليف والربحية', 'icon' => '📈', 'group' => 'التعاقد والمالية', 'description' => 'مقارنة الإيراد بالتكلفة الداخلية (WAC) للحالات المُسلَّمة ومجمل الربح'],
            ] as $extra) {
                $cards[] = $extra;
            }
        }

        return $cards;
    }

    public function sectionMeta(string $section): ?array
    {
        return collect($this->sections())->firstWhere('id', $section);
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    public function build(string $section, ?Carbon $from, ?Carbon $to): array
    {
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $from = $from?->copy()->startOfDay();
        $to = $to?->copy()->endOfDay();

        return match ($section) {
            'cash-income' => $this->buildCashIncome($from, $to),
            'financial' => $this->buildFinancial($from, $to),
            'inventory' => $this->buildInventoryAnalytics($from, $to),
            'operations' => $this->buildOperations($from, $to),
            'bom' => $this->buildBom($from, $to),
            'patient-tracks' => $this->buildPatientTracks($from, $to),
            'cases' => $this->buildCases($from, $to),
            'spec-edit-requests' => $this->buildSpecEditRequests($from, $to),
            'visit-types' => $this->buildVisitTypes($from, $to),
            'stock-categories' => $this->buildStockCategories($from, $to),
            'catalog' => $this->buildCatalog($from, $to),
            'inventory-overview' => $this->buildInventoryMovements($from, $to),
            'inventory-valuation' => $this->buildInventoryValuation($from, $to),
            'item-margins' => $this->buildItemMargins($from, $to),
            'inventory-reconciliation' => $this->buildInventoryReconciliation($from, $to),
            'suppliers' => $this->buildSuppliers($from, $to),
            'returns' => $this->buildReturns($from, $to),
            'companies' => $this->buildCompanies($from, $to),
            'contracts' => $this->buildContracts($from, $to),
            'civilian-debts' => $this->buildCivilianDebts($from, $to),
            'audit' => $this->buildAudit($from, $to),
            'services-approvals' => $this->buildServicesApprovals($from, $to),
            'workshop-sections' => $this->buildWorkshopSections($from, $to),
            'workshop-tracking' => $this->buildWorkshopTracking($from, $to),
            'dispense-approvals' => $this->buildDispenseApprovals($from, $to),
            'opening-balance' => $this->buildOpeningBalance($from, $to),
            'closing-balance' => $this->buildClosingBalance($from, $to),
            'profitability' => $this->buildProfitability($from, $to),
            default => throw new InvalidArgumentException("تقرير غير معروف: {$section}"),
        };
    }

    /** @return array{from: ?Carbon, to: ?Carbon} */
    public function parseDateRange(?string $from, ?string $to): array
    {
        return ClinicTime::parseDateRange($from, $to);
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildCashIncome(?Carbon $from, ?Carbon $to): array
    {
        $payments = $this->constrainDateRange(
            Payment::query()->with('caseRecord:id,case_no'),
            'received_at',
            $from,
            $to,
        )
            ->orderByDesc('received_at')
            ->limit(1000)
            ->get();

        $rows = $payments->map(fn (Payment $p) => [
            ClinicTime::format($p->received_at, 'd/m/Y H:i'),
            $p->payment_no ?? '—',
            $p->patient_name ?? '—',
            $p->caseRecord?->case_no ?? '—',
            PaymentMethod::labelFor($p->method),
            $p->reference ?? '—',
            $p->received_by ?? '—',
            number_format((float) $p->amount, 2).' ج.م',
        ])->values()->all();

        return [
            'title' => 'التحصيل النقدي — الخزنة',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['التاريخ', 'رقم الدفعة', 'المريض', 'رقم الحالة', 'الوسيلة', 'رقم العملية', 'المُحصِّل', 'المبلغ'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildFinancial(?Carbon $from, ?Carbon $to): array
    {
        $delivered = $this->constrainDateRange(
            CaseRecord::query()
                ->with('patient:id,name')
                ->where('patient_type', Patient::TYPE_CIVILIAN)
                ->where('stage_key', CaseRecord::STAGE_DELIVERED),
            'delivered_at',
            $from,
            $to,
        )
            ->orderByDesc('delivered_at')
            ->get();

        $rows = $delivered->map(fn (CaseRecord $c) => [
            $c->case_no ?? '—',
            $c->patient?->name ?? '—',
            $c->work_order_no ?? '—',
            $c->invoice_no ?? '—',
            number_format(CaseFinancialSummary::totalCost($c), 2).' ج.م',
            number_format((float) ($c->issue_cost ?? $c->internal_cost ?? 0), 2).' ج.م',
        ])->values()->all();

        return [
            'title' => 'الإيرادات والمالية',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['رقم الحالة', 'المريض', 'أمر التشغيل', 'الفاتورة', 'الإيراد', 'تكلفة WAC (صرف)'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildInventoryAnalytics(?Carbon $from, ?Carbon $to): array
    {
        $stagnantCutoff = ($to ?? ClinicTime::now())->copy()->subDays(180)->startOfDay();

        $items = StockItem::query()
            ->orderBy('code')
            ->limit(500)
            ->get();

        $rows = $items->map(function (StockItem $item) use ($stagnantCutoff) {
            $status = $this->stockActivityStatus($item, $stagnantCutoff);

            return [
                $item->code ?? '—',
                $item->name ?? '—',
                (string) ($item->qty ?? 0),
                $item->last_moved_at ? ClinicTime::format($item->last_moved_at, 'd/m/Y') : '—',
                $status,
            ];
        })->values()->all();

        return [
            'title' => 'تحليلات المخزون',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['رقم الصنف', 'اسم الصنف', 'الرصيد', 'آخر حركة', 'الحالة'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildOperations(?Carbon $from, ?Carbon $to): array
    {
        $cases = $this->constrainDateRange(
            CaseRecord::query()
                ->with('patient:id,name')
                ->whereNotNull('work_order_no'),
            'updated_at',
            $from,
            $to,
        )
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $rows = $cases->map(fn (CaseRecord $c) => [
            $c->work_order_no ?? '—',
            $c->patient?->name ?? '—',
            CaseStage::labelFor($c->stage_key),
            ClinicTime::format($c->updated_at, 'd/m/Y H:i'),
        ])->values()->all();

        return [
            'title' => 'التشغيل والأوامر',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['أمر التشغيل', 'المريض', 'المرحلة', 'آخر تحديث'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildBom(?Carbon $from, ?Carbon $to): array
    {
        $snapshot = $this->snapshotReports->build($from, $to);
        $bomRows = $snapshot['bom']['rows'] ?? [];

        $rows = collect($bomRows)->map(fn (array $row) => [
            $row['patient'] ?? '—',
            $row['work_order_no'] ?? '—',
            $row['stage_label'] ?? '—',
            (string) ($row['line_count'] ?? 0),
            number_format((float) ($row['value'] ?? 0), 2).' ج.م',
        ])->values()->all();

        return [
            'title' => 'قوائم المواد',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['المريض', 'أمر التشغيل', 'المرحلة', 'البنود', 'قيمة قائمة المواد'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildPatientTracks(?Carbon $from, ?Carbon $to): array
    {
        $tracks = $this->filterPatientTracksByDate(
            $this->patientTracks->list(limit: 500),
            $from,
            $to,
        );

        $rows = $tracks->map(fn (array $track) => [
            $track['name'] ?? '—',
            $track['case_no'] ?? '—',
            $track['company_name'] ?? '—',
            $track['stage_label'] ?? CaseStage::labelFor($track['stage_key'] ?? ''),
        ])->values()->all();

        return [
            'title' => 'مسار المرضى',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['المريض', 'رقم الحالة', 'الجهة', 'المرحلة'],
            'rows' => $rows,
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $tracks */
    private function filterPatientTracksByDate(Collection $tracks, ?Carbon $from, ?Carbon $to): Collection
    {
        if (! $from && ! $to) {
            return $tracks;
        }

        return $tracks
            ->filter(function (array $track) use ($from, $to) {
                $at = $this->patientTrackFilterDate($track);

                if (! $at) {
                    return true;
                }

                if ($from && $at->lt($from)) {
                    return false;
                }

                if ($to && $at->gt($to)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /** @param  array<string, mixed>  $track */
    private function patientTrackFilterDate(array $track): ?Carbon
    {
        $details = $track['patient_details'] ?? [];
        $registered = $details['registered_at'] ?? null;

        if ($registered) {
            return Carbon::parse($registered, ClinicTime::zone())->startOfDay();
        }

        foreach ($details['cases'] ?? [] as $case) {
            if (! empty($case['created_at'])) {
                return Carbon::parse($case['created_at'], ClinicTime::zone())->startOfDay();
            }
        }

        return null;
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildCases(?Carbon $from, ?Carbon $to): array
    {
        $cases = CaseRecord::query()
            ->with('patient:id,name')
            ->when($from || $to, function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->where(function ($q2) use ($from, $to) {
                        $this->constrainDateRange($q2, 'updated_at', $from, $to);
                    })->orWhere(function ($q2) use ($from, $to) {
                        $this->constrainDateRange($q2, 'delivered_at', $from, $to);
                    });
                });
            })
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $rows = $cases->map(fn (CaseRecord $c) => [
            $c->case_no ?? '—',
            $c->patient?->name ?? '—',
            CaseStage::labelFor($c->stage_key),
            $c->work_order_no ?? '—',
            ClinicTime::format($c->delivered_at ?? $c->updated_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title' => 'متابعة الحالات',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['رقم الحالة', 'المريض', 'المرحلة', 'أمر التشغيل', 'التاريخ'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildVisitTypes(?Carbon $from, ?Carbon $to): array
    {
        $appointments = Appointment::query()
            ->with('visitTypeRecord:id,name')
            ->when($from || $to, function ($q) use ($from, $to) {
                if ($from && $to) {
                    $q->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()]);
                } elseif ($from) {
                    $q->where('appointment_date', '>=', $from->toDateString());
                } else {
                    $q->where('appointment_date', '<=', $to->toDateString());
                }
            })
            ->get();

        $grouped = $appointments->groupBy(fn (Appointment $a) => $a->displayVisitType());

        $rows = $grouped->map(fn (Collection $group, string $label) => [
            $label,
            (string) $group->count(),
        ])->values()->all();

        return [
            'title' => 'أنواع الزيارات',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['نوع الزيارة', 'العدد'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildStockCategories(?Carbon $from, ?Carbon $to): array
    {
        $categories = StockCategory::query()
            ->withCount(['stockItems', 'fields'])
            ->orderBy('name')
            ->limit(500)
            ->get();

        $rows = $categories->map(fn (StockCategory $category) => [
            $category->name ?? '—',
            (string) $category->stock_items_count,
            (string) $category->fields_count,
            ClinicTime::format($category->created_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title' => 'أقسام الأصناف',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['القسم', 'عدد الأصناف', 'حقول مخصصة', 'تاريخ الإضافة'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildCatalog(?Carbon $from, ?Carbon $to): array
    {
        $batches = $this->priceBatchesInDateRange($from, $to)
            ->with(['stockItem' => fn ($q) => $q->select('id', 'code', 'name')->withCount('prices')])
            ->orderByRaw('COALESCE(received_at, DATE(created_at)) DESC')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $rowActions = [];

        $rows = $batches->map(function (StockItemPrice $p) use (&$rowActions) {
            $priceCount = (int) ($p->stockItem?->prices_count ?? 0);
            $multiPrice = $priceCount > 1;
            $receivedAt = $p->received_at ?? $p->created_at;

            $rowActions[] = [
                'stock_item_id' => (int) $p->stock_item_id,
            ];

            return [
                $p->stockItem?->code ?? '—',
                $p->stockItem?->name ?? '—',
                number_format((float) $p->amount, 2).' ج.م',
                (string) $p->qty,
                ClinicTime::format($receivedAt, 'd/m/Y'),
                $multiPrice ? ('نعم ('.$priceCount.' أسعار)') : 'لا',
            ];
        })->values()->all();

        return [
            'title' => 'الأصناف والأسعار',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['رقم الصنف', 'اسم الصنف', 'السعر', 'رصيد أول المده', 'تاريخ الاستلام', 'أسعار متعددة'],
            'rows' => $rows,
            'row_actions' => $rowActions,
        ];
    }

    /** @param Builder<StockItemPrice> $query */
    private function priceBatchesInDateRange(?Carbon $from, ?Carbon $to): Builder
    {
        if (! $from && ! $to) {
            return StockItemPrice::query();
        }

        $fromDate = $from ? ClinicTime::format($from, 'Y-m-d') : null;
        $toDate = $to ? ClinicTime::format($to, 'Y-m-d') : null;

        return StockItemPrice::query()->where(function ($q) use ($from, $to, $fromDate, $toDate) {
            $q->where(function ($inner) use ($fromDate, $toDate) {
                $inner->whereNotNull('received_at');
                if ($fromDate) {
                    $inner->whereDate('received_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $inner->whereDate('received_at', '<=', $toDate);
                }
            })->orWhere(function ($inner) use ($from, $to) {
                $inner->whereNull('received_at');
                if ($from) {
                    $inner->where('created_at', '>=', $from);
                }
                if ($to) {
                    $inner->where('created_at', '<=', $to);
                }
            });
        });
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildInventoryValuation(?Carbon $from, ?Carbon $to): array
    {
        $items = StockItem::query()
            ->with(['prices' => fn ($q) => $q->orderByDesc('received_at')->orderByDesc('id')])
            ->orderBy('code')
            ->get();

        $totalQty = 0;
        $totalValue = 0.0;
        $totalHighestValue = 0.0;
        $rows = [];

        foreach ($items as $item) {
            $analytics = $this->itemPricingAnalytics->rowForItem($item);
            $qty = (int) ($analytics['qty'] ?? 0);
            $wac = (float) ($analytics['wac'] ?? 0);
            $lineValue = (float) ($analytics['wac_inventory_value'] ?? 0);
            $highestLineValue = (float) ($analytics['highest_inventory_value'] ?? 0);
            $totalQty += $qty;
            $totalValue += $lineValue;
            $totalHighestValue += $highestLineValue;

            $priceLabels = $item->prices
                ->map(fn (StockItemPrice $p) => number_format((float) $p->amount, 2).' ج.م'
                    .($p->qty ? ' ×'.$p->qty : '')
                    .($p->received_at ? ' ('.ClinicTime::format($p->received_at, 'd/m/Y').')' : ''))
                ->values()
                ->all();

            $rows[] = [
                $item->code ?? '—',
                $item->name ?? '—',
                (string) $qty,
                (string) $item->catalogBalance(),
                number_format($wac, 4).' ج.م',
                number_format((float) ($analytics['highest_purchase_price'] ?? 0), 4).' ج.م',
                number_format((float) ($analytics['unit_margin'] ?? 0), 4).' ج.م',
                $priceLabels !== [] ? implode(' · ', $priceLabels) : '—',
                number_format($lineValue, 2).' ج.م',
                number_format($highestLineValue, 2).' ج.م',
            ];
        }

        return [
            'title' => 'تقييم المخزون',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [
                ['label' => 'عدد الأصناف', 'value' => (string) $items->count()],
                ['label' => 'إجمالي الكميات', 'value' => (string) $totalQty],
                ['label' => 'قيمة المخزون (WAC)', 'value' => number_format($totalValue, 2).' ج.م'],
                ['label' => 'قيمة المخزون (أعلى سعر)', 'value' => number_format($totalHighestValue, 2).' ج.م'],
            ],
            'headers' => ['رقم الصنف', 'اسم الصنف', 'رصيد المخزن', 'رصيد كتالوج', 'WAC', 'أعلى سعر', 'هامش الوحدة', 'أسعار الشراء', 'قيمة WAC', 'قيمة أعلى سعر'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildInventoryMovements(?Carbon $from, ?Carbon $to): array
    {
        $movements = $this->constrainDateRange(
            StockMovement::query()->with('stockItem:id,code,name'),
            'moved_at',
            $from,
            $to,
        )
            ->orderByDesc('moved_at')
            ->limit(500)
            ->get();

        $rows = $movements->map(fn (StockMovement $m) => [
            ClinicTime::format($m->moved_at, 'd/m/Y H:i'),
            $this->movementTypeLabel($m),
            $m->stockItem?->code ?? '—',
            $m->stockItem?->name ?? '—',
            (string) $this->signedMovementQuantity($m),
            number_format((float) ($m->unit_cost ?? 0), 4).' ج.م',
            number_format(abs((int) $m->quantity) * (float) ($m->unit_cost ?? 0), 2).' ج.م',
            $this->movementReferenceLabel($m),
        ])->values()->all();

        return [
            'title' => 'متابعة حركة الأصناف',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['التاريخ', 'النوع', 'رقم الصنف', 'اسم الصنف', 'الكمية', 'WAC/تكلفة', 'قيمة الحركة', 'المرجع'],
            'rows' => $rows,
        ];
    }

    private function movementTypeLabel(StockMovement $movement): string
    {
        return match ($movement->movement_type) {
            StockMovement::TYPE_ISSUE => 'صرف / بيع',
            StockMovement::TYPE_RETURN => 'ارتجاع من الورشة',
            StockMovement::TYPE_RECEIVE => 'توريد',
            default => $movement->movement_type ?? '—',
        };
    }

    /** كمية موقّعة للعرض: موجب للصرف، سالب للارتجاع من الورشة. */
    private function signedMovementQuantity(StockMovement $movement): int
    {
        $qty = (int) $movement->quantity;

        return match ($movement->movement_type) {
            StockMovement::TYPE_ISSUE => abs($qty),
            StockMovement::TYPE_RETURN => -abs($qty),
            StockMovement::TYPE_RECEIVE => abs($qty),
            default => $qty,
        };
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>, row_actions: list<array<string, mixed>>} */
    private function buildReturns(?Carbon $from, ?Carbon $to): array
    {
        $notes = ReturnNote::query()
            ->with('lines')
            ->whereIn('status', [ReturnNote::STATUS_PARTIAL, ReturnNote::STATUS_COMPLETED])
            ->whereHas('lines', fn ($q) => $q->where('qty_returned', '>', 0))
            ->when($from || $to, fn ($q) => $this->constrainDateRange($q, 'updated_at', $from, $to))
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $rowActions = $notes->map(function (ReturnNote $n) {
            $receivedLines = $n->lines
                ->filter(fn ($line) => (int) $line->qty_returned > 0)
                ->values();

            return [
                'return_no' => $n->return_no ?? '—',
                'patient_name' => $n->patient_name ?? '—',
                'work_order_no' => $n->work_order_no ?? '—',
                'warehouse_received_at' => ClinicTime::format($n->completed_at ?? $n->updated_at, 'd/m/Y H:i'),
                'can_view_items' => $receivedLines->isNotEmpty(),
                'lines' => $receivedLines->map(fn ($line) => [
                    'code' => $line->stock_item_code,
                    'name' => $line->name ?: $line->stock_item_code,
                    'qty_returned' => (int) $line->qty_returned,
                    'reason' => $line->reason ?? '—',
                ])->values()->all(),
            ];
        })->values()->all();

        $rows = $notes->map(function (ReturnNote $n) {
            $receivedCount = $n->lines->filter(fn ($line) => (int) $line->qty_returned > 0)->count();

            return [
                $n->return_no ?? '—',
                $n->patient_name ?? '—',
                $n->work_order_no ?? '—',
                (string) $receivedCount,
                ClinicTime::format($n->completed_at ?? $n->updated_at, 'd/m/Y'),
            ];
        })->values()->all();

        return [
            'title' => 'طلبات الارتجاع',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['رقم الطلب', 'المريض', 'أمر التشغيل', 'البنود', 'تاريخ الاستلام'],
            'rows' => $rows,
            'row_actions' => $rowActions,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildSpecEditRequests(?Carbon $from, ?Carbon $to): array
    {
        $requests = $this->constrainDateRange(
            SpecEditRequest::query()->with([
                'techOrderSpec:id,order_ref,patient_name',
                'caseRecord:id,case_no,order_ref',
                'requestedBy:id,name',
            ]),
            'created_at',
            $from,
            $to,
        )
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $rows = $requests->map(fn (SpecEditRequest $r) => [
            $r->caseRecord?->case_no ?? '—',
            $r->techOrderSpec?->patient_name ?? '—',
            $r->techOrderSpec?->order_ref ?? $r->caseRecord?->order_ref ?? '—',
            $r->status->label(),
            $r->requestedBy?->name ?? '—',
            (string) count($r->proposed_items ?? []),
            ClinicTime::format($r->created_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title' => 'طلبات تعديل التوصيف',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['رقم الحالة', 'المريض', 'مرجع الطلب', 'الحالة', 'طلب بواسطة', 'البنود', 'التاريخ'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildSuppliers(?Carbon $from, ?Carbon $to): array
    {
        $suppliers = $this->supplierService->listForAdmin(
            null,
            $from?->toDateString(),
            $to?->toDateString(),
        );

        $rows = $suppliers->map(fn (Supplier $s) => [
            $s->name ?? '—',
            $s->phone ?? '—',
            $s->email ?? '—',
            (string) ($s->linked_items_count ?? 0),
            number_format((float) ($s->debt_total ?? 0), 2).' ج.م',
            ClinicTime::format($s->created_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title' => 'الموردون',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['المورد', 'الهاتف', 'البريد', 'أصناف مرتبطة', 'المديونية', 'تاريخ الإضافة'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildCompanies(?Carbon $from, ?Carbon $to): array
    {
        $companies = $this->constrainDateRange(
            ContractCompany::query(),
            'created_at',
            $from,
            $to,
        )
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $rows = $companies->map(fn (ContractCompany $c) => [
            $c->company_code ?? '—',
            $c->name ?? '—',
            $this->companyBillingTypeLabel($c),
            $this->companyEntityLabel($c),
            $this->companyClassificationLabel($c),
        ])->values()->all();

        return [
            'title' => 'جهات التعاقد',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['الكود', 'الاسم', 'النوع', 'الجهة', 'التصنيف'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildContracts(?Carbon $from, ?Carbon $to): array
    {
        $contracts = ApprovalContract::query()
            ->when($from || $to, function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->where(function ($q2) use ($from, $to) {
                        if ($from && $to) {
                            $q2->whereBetween('approval_date', [$from->toDateString(), $to->toDateString()]);
                        } elseif ($from) {
                            $q2->where('approval_date', '>=', $from->toDateString());
                        } else {
                            $q2->where('approval_date', '<=', $to->toDateString());
                        }
                    })->orWhere(function ($q2) use ($from, $to) {
                        $this->constrainDateRange($q2, 'created_at', $from, $to);
                    });
                });
            })
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $rows = $contracts->map(fn (ApprovalContract $c) => [
            $c->contract_no ?? '—',
            $c->patient_name ?? '—',
            $c->company_name ?? '—',
            number_format((float) $c->approved_amount, 2).' ج.م',
            ClinicTime::format($c->approval_date ?? $c->created_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title' => 'موافقات جهات التعاقد',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['رقم العقد', 'المريض', 'الجهة', 'المبلغ', 'التاريخ'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildCivilianDebts(?Carbon $from, ?Carbon $to): array
    {
        $entries = $this->constrainDateRange(
            DebtCollectionEntry::query()
                ->with(['payable' => fn ($q) => $q->with('contractCompany:id,name,company_code')])
                ->where('payable_type', ContractCompanyDebt::class),
            'collected_at',
            $from,
            $to,
        )
            ->orderByDesc('collected_at')
            ->limit(500)
            ->get();

        $rows = $entries->map(function (DebtCollectionEntry $e) {
            $debt = $e->payable instanceof ContractCompanyDebt ? $e->payable : null;
            $company = $debt?->contractCompany;

            return [
                ClinicTime::format($e->collected_at, 'd/m/Y'),
                $company?->name ?? '—',
                number_format((float) $e->amount, 2).' ج.م',
            ];
        })->values()->all();

        return [
            'title' => 'المديونات',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['التاريخ', 'الجهة', 'المبلغ'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildAudit(?Carbon $from, ?Carbon $to): array
    {
        $logs = $this->constrainDateRange(
            AuditLog::query(),
            'logged_at',
            $from,
            $to,
        )
            ->orderByDesc('logged_at')
            ->limit(500)
            ->get();

        $rows = $logs->map(fn (AuditLog $log) => [
            ClinicTime::format($log->logged_at, 'd/m/Y H:i'),
            $log->user_name ?? '—',
            $log->action ?? '—',
            $log->tag ?? '—',
            Str::limit($log->description ?? '—', 80),
        ])->values()->all();

        return [
            'title' => 'سجل الرقابة',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['التاريخ', 'المستخدم', 'الإجراء', 'الوسم', 'الوصف'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildServicesApprovals(?Carbon $from, ?Carbon $to): array
    {
        $approvals = $this->constrainDateRange(
            ServicesApproval::query()->with([
                'caseRecord:id,case_no,patient_id',
                'caseRecord.patient:id,name,patient_code,military_beneficiary_category',
                'approvedBy:id,name',
            ]),
            'created_at',
            $from,
            $to,
        )
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $rows = $approvals->map(fn (ServicesApproval $a) => [
            $a->caseRecord?->case_no ?? '—',
            $a->caseRecord?->patient?->name ?? '—',
            $this->servicesApprovalStatusLabel($a->status),
            $a->approvedBy?->name ?? '—',
            ClinicTime::format($a->approved_at ?? $a->created_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title' => 'تصديقات إدارة الخدمات',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [
                ['label' => 'إجمالي الطلبات', 'value' => (string) $approvals->count()],
                ['label' => 'معلّقة', 'value' => (string) $approvals->where('status', ServicesApproval::STATUS_PENDING)->count()],
            ],
            'headers' => ['رقم الحالة', 'المريض', 'الحالة', 'المُصدِّق', 'التاريخ'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildWorkshopSections(?Carbon $from, ?Carbon $to): array
    {
        $sections = WorkshopSection::query()
            ->withCount('technicians')
            ->when($from || $to, fn ($q) => $this->constrainDateRange($q, 'created_at', $from, $to))
            ->orderBy('sort')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $rows = $sections->map(fn (WorkshopSection $s) => [
            $s->name ?? '—',
            $s->code ?? '—',
            (string) $s->technicians_count,
            $s->active ? 'نشط' : 'موقوف',
            ClinicTime::format($s->created_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title' => 'أقسام الورشة',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [
                ['label' => 'عدد الأقسام', 'value' => (string) $sections->count()],
                ['label' => 'نشطة', 'value' => (string) $sections->where('active', true)->count()],
            ],
            'headers' => ['القسم', 'الكود', 'عدد الفنيين', 'الحالة', 'تاريخ الإضافة'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildWorkshopTracking(?Carbon $from, ?Carbon $to): array
    {
        $cases = $this->constrainDateRange(
            CaseRecord::query()
                ->where('stage_key', CaseRecord::STAGE_MANUFACTURING)
                ->whereHas('bom', fn ($q) => $q->where('stage', Bom::STAGE_WIP))
                ->with([
                    'patient:id,name',
                    'workshopSection:id,name',
                    'assignedTechnician:id,name',
                ]),
            'updated_at',
            $from,
            $to,
        )
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $rows = $cases->map(fn (CaseRecord $c) => [
            $c->case_no ?? '—',
            $c->work_order_no ?? '—',
            $c->patient?->name ?? '—',
            $c->workshopSection?->name ?? '—',
            $c->assignedTechnician?->name ?? '—',
            ManufacturingStage::tryFrom($c->manufacturing_stage ?? '')?->label()
                ?? ($c->manufacturing_stage ?? '—'),
            (string) ((int) ($c->workshop_progress_pct ?? 0)).'%',
            ClinicTime::format($c->updated_at, 'd/m/Y H:i'),
        ])->values()->all();

        return [
            'title' => 'تتبع أوامر الشغل — الورشة',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [
                ['label' => 'أوامر تحت التشغيل', 'value' => (string) $cases->count()],
                ['label' => 'مُسندة لفني', 'value' => (string) $cases->whereNotNull('assigned_technician_id')->count()],
            ],
            'headers' => ['رقم الحالة', 'أمر الشغل', 'المريض', 'قسم الورشة', 'الفني', 'مرحلة التصنيع', 'التقدم', 'آخر تحديث'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildDispenseApprovals(?Carbon $from, ?Carbon $to): array
    {
        $requests = $this->constrainDateRange(
            StockDispenseRequest::query()->with([
                'caseRecord:id,case_no,work_order_no,patient_id',
                'caseRecord.patient:id,name',
                'requestedBy:id,name',
                'approvedBy:id,name',
            ]),
            'created_at',
            $from,
            $to,
        )
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $rows = $requests->map(fn (StockDispenseRequest $r) => [
            $r->work_order_no ?? '—',
            $r->caseRecord?->patient?->name ?? '—',
            $r->caseRecord?->case_no ?? '—',
            (string) count($r->lines ?? []),
            $this->dispenseRequestStatusLabel($r->status),
            $r->requestedBy?->name ?? '—',
            $r->approvedBy?->name ?? '—',
            ClinicTime::format($r->approved_at ?? $r->created_at, 'd/m/Y H:i'),
        ])->values()->all();

        return [
            'title' => 'اعتمادات صرف المخزن',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [
                ['label' => 'إجمالي الطلبات', 'value' => (string) $requests->count()],
                ['label' => 'معلّقة', 'value' => (string) $requests->where('status', StockDispenseRequest::STATUS_PENDING)->count()],
            ],
            'headers' => ['أمر الشغل', 'المريض', 'رقم الحالة', 'البنود', 'الحالة', 'طلب بواسطة', 'اعتمد بواسطة', 'التاريخ'],
            'rows' => $rows,
        ];
    }

    private function servicesApprovalStatusLabel(?string $status): string
    {
        return match ($status) {
            ServicesApproval::STATUS_PENDING => 'معلّق',
            ServicesApproval::STATUS_APPROVED => 'موافق',
            ServicesApproval::STATUS_REJECTED => 'مرفوض',
            default => $status ?? '—',
        };
    }

    private function dispenseRequestStatusLabel(?string $status): string
    {
        return match ($status) {
            StockDispenseRequest::STATUS_PENDING => 'معلّق',
            StockDispenseRequest::STATUS_APPROVED => 'موافق',
            StockDispenseRequest::STATUS_REJECTED => 'مرفوض',
            StockDispenseRequest::STATUS_EXECUTED => 'منفّذ',
            default => $status ?? '—',
        };
    }

    /** @return array{from: Carbon, to: Carbon} */
    private function resolveFinanceRange(?Carbon $from, ?Carbon $to): array
    {
        $now = ClinicTime::now();

        return [
            'from' => ($from ?? $now->copy()->startOfMonth())->copy()->startOfDay(),
            'to' => ($to ?? $now->copy()->endOfMonth())->copy()->endOfDay(),
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private function financeDomains(): array
    {
        $domains = [
            ['key' => FinancialBalanceService::DOMAIN_CASH, 'label' => 'الخزنة النقدية'],
            ['key' => FinancialBalanceService::DOMAIN_CIVILIAN, 'label' => 'مديونية الجهات المدنية'],
        ];

        if (Gate::allows('view-military-profit')) {
            $domains[] = ['key' => FinancialBalanceService::DOMAIN_MILITARY, 'label' => 'المستحق السيادي (عسكري)'];
        }

        $domains[] = ['key' => FinancialBalanceService::DOMAIN_INVENTORY, 'label' => 'قيمة المخزون (تقريبي)'];

        return $domains;
    }

    private function money(float $value): string
    {
        if (! CaseFinancialSummary::canSeeRevenue()) {
            return '—';
        }

        return number_format($value, 2).' ج.م';
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildOpeningBalance(?Carbon $from, ?Carbon $to): array
    {
        $range = $this->resolveFinanceRange($from, $to);
        $balances = $this->balanceService->balances($range['from'], $range['to']);

        $rows = [];
        $total = 0.0;
        foreach ($this->financeDomains() as $domain) {
            $opening = (float) ($balances[$domain['key']]['opening'] ?? 0);
            $total += $opening;
            $rows[] = [$domain['label'], $this->money($opening)];
        }

        return [
            'title' => 'رصيد أول المدة',
            'period_label' => $this->periodLabel($range['from'], $range['to']),
            'summary' => [
                ['label' => 'إجمالي رصيد أول المدة', 'value' => $this->money($total)],
            ],
            'headers' => ['المجال', 'رصيد أول المدة'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildClosingBalance(?Carbon $from, ?Carbon $to): array
    {
        $range = $this->resolveFinanceRange($from, $to);
        $balances = $this->balanceService->balances($range['from'], $range['to']);

        $rows = [];
        $totalClosing = 0.0;
        foreach ($this->financeDomains() as $domain) {
            $data = $balances[$domain['key']] ?? [];
            $opening = (float) ($data['opening'] ?? 0);
            $movement = (float) ($data['movement'] ?? 0);
            $closing = (float) ($data['closing'] ?? 0);
            $totalClosing += $closing;
            $rows[] = [$domain['label'], $this->money($opening), $this->money($movement), $this->money($closing)];
        }

        return [
            'title' => 'رصيد آخر المدة',
            'period_label' => $this->periodLabel($range['from'], $range['to']),
            'summary' => [
                ['label' => 'إجمالي رصيد آخر المدة', 'value' => $this->money($totalClosing)],
            ],
            'headers' => ['المجال', 'رصيد أول المدة', 'حركة الفترة', 'رصيد آخر المدة'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildProfitability(?Carbon $from, ?Carbon $to): array
    {
        $range = $this->resolveFinanceRange($from, $to);
        $report = $this->profitabilityService->report($range['from'], $range['to']);
        $showMilitary = Gate::allows('view-military-profit');

        $cases = collect($report['cases'])
            ->reject(fn (array $row) => ! $showMilitary && ($row['patient_type'] ?? null) === Patient::TYPE_MILITARY)
            ->values();

        $rows = $cases->map(fn (array $row) => [
            $row['case_no'] ?? '—',
            $row['patient_name'] ?? '—',
            ($row['patient_type'] ?? null) === Patient::TYPE_MILITARY ? 'عسكري' : 'مدني',
            $row['company'] ?? '—',
            $this->money((float) ($row['revenue'] ?? 0)),
            $this->money((float) ($row['cost'] ?? 0)),
            $this->money((float) ($row['margin'] ?? 0)),
            number_format((float) ($row['margin_pct'] ?? 0), 2).'%',
        ])->values()->all();

        $revenue = (float) $cases->sum('revenue');
        $cost = (float) $cases->sum('cost');
        $margin = round($revenue - $cost, 2);
        $marginPct = $revenue > 0 ? round(($margin / $revenue) * 100, 2) : 0.0;

        return [
            'title' => 'مراجعة التكاليف والربحية',
            'period_label' => $this->periodLabel($range['from'], $range['to']),
            'summary' => [
                ['label' => 'عدد الحالات المُسلَّمة', 'value' => (string) $cases->count()],
                ['label' => 'إجمالي الإيراد', 'value' => $this->money($revenue)],
                ['label' => 'إجمالي التكلفة (WAC)', 'value' => $this->money($cost)],
                ['label' => 'مجمل الربح', 'value' => $this->money($margin)],
                ['label' => 'نسبة الربح', 'value' => number_format($marginPct, 2).'%'],
            ],
            'headers' => ['رقم الحالة', 'المريض', 'النوع', 'الجهة', 'الإيراد', 'التكلفة', 'مجمل الربح', 'نسبة الربح'],
            'rows' => $rows,
        ];
    }

    private function periodLabel(?Carbon $from, ?Carbon $to): string
    {
        if (! $from && ! $to) {
            return '';
        }

        if ($from && $to) {
            return ClinicTime::format($from, 'd/m/Y').' — '.ClinicTime::format($to, 'd/m/Y');
        }

        if ($from) {
            return 'من '.ClinicTime::format($from, 'd/m/Y');
        }

        return 'حتى '.ClinicTime::format($to, 'd/m/Y');
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Builder
     *
     * @param  T  $query
     * @return T
     */
    private function constrainDateRange($query, string $column, ?Carbon $from, ?Carbon $to)
    {
        if ($from && $to) {
            return $query->whereBetween($column, [$from, $to]);
        }

        if ($from) {
            return $query->where($column, '>=', $from);
        }

        if ($to) {
            return $query->where($column, '<=', $to);
        }

        return $query;
    }

    private function companyBillingTypeLabel(ContractCompany $company): string
    {
        if ($company->is_military) {
            return '—';
        }

        return $company->is_contracted ? 'مدني متعاقد' : 'مدني نقدي';
    }

    private function companyEntityLabel(ContractCompany $company): string
    {
        if ($company->is_military) {
            return Patient::MILITARY_SOVEREIGN_ENTITY;
        }

        return $company->name ?? '—';
    }

    private function companyClassificationLabel(ContractCompany $company): string
    {
        if ($company->is_military) {
            return 'عسكري';
        }

        return $company->is_contracted ? 'مدني' : 'جهات';
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildItemMargins(?Carbon $from, ?Carbon $to): array
    {
        $rows = collect($this->itemPricingAnalytics->catalogMargins())
            ->map(fn (array $row) => [
                $row['code'] ?? '—',
                $row['name'] ?? '—',
                (string) ($row['qty'] ?? 0),
                number_format((float) ($row['wac'] ?? 0), 4).' ج.م',
                number_format((float) ($row['highest_purchase_price'] ?? 0), 4).' ج.م',
                number_format((float) ($row['lowest_purchase_price'] ?? 0), 4).' ج.م',
                (string) ($row['price_batch_count'] ?? 0),
                number_format((float) ($row['unit_margin'] ?? 0), 4).' ج.م',
                number_format((float) ($row['margin_pct'] ?? 0), 2).'%',
                number_format((float) ($row['wac_inventory_value'] ?? 0), 2).' ج.م',
            ])
            ->values()
            ->all();

        return [
            'title' => 'هامش الربح بالأصناف',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [
                ['label' => 'عدد الأصناف', 'value' => (string) count($rows)],
            ],
            'headers' => ['رقم الصنف', 'اسم الصنف', 'الرصيد', 'WAC', 'أعلى سعر', 'أدنى سعر', 'دفعات', 'هامش الوحدة', 'نسبة الهامش', 'قيمة WAC'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildInventoryReconciliation(?Carbon $from, ?Carbon $to): array
    {
        $range = $this->resolveFinanceRange($from, $to);
        $summary = $this->reconciliationService->periodSummary($range['from'], $range['to']);
        $inv = $summary['inventory'];
        $rev = $summary['revenue'];

        return [
            'title' => 'تسوية مخزون ↔ مالية',
            'period_label' => $this->periodLabel($range['from'], $range['to']),
            'summary' => [
                ['label' => 'قيمة التوريد (WAC)', 'value' => $this->money((float) ($inv['received_value'] ?? 0))],
                ['label' => 'قيمة الصرف (WAC)', 'value' => $this->money((float) ($inv['issued_value'] ?? 0))],
                ['label' => 'إيراد التسليم', 'value' => $this->money((float) ($rev['delivered_revenue'] ?? 0))],
                ['label' => 'تكلفة WAC للتسليم', 'value' => $this->money((float) ($rev['delivered_wac_cost'] ?? 0))],
                ['label' => 'مجمل الربح', 'value' => $this->money((float) ($rev['gross_margin'] ?? 0))],
                ['label' => 'نسبة الربح', 'value' => number_format((float) ($rev['margin_pct'] ?? 0), 2).'%'],
            ],
            'headers' => ['البند', 'القيمة'],
            'rows' => [
                ['توريد مخزني (قيمة WAC)', $this->money((float) ($inv['received_value'] ?? 0))],
                ['صرف مخزني (قيمة WAC)', $this->money((float) ($inv['issued_value'] ?? 0))],
                ['مرتجعات للمخزن', $this->money((float) ($inv['returned_value'] ?? 0))],
                ['صافي خروج مخزني', $this->money((float) ($inv['net_outflow'] ?? 0))],
                ['حالات مُسلَّمة', (string) ($rev['delivered_count'] ?? 0)],
                ['إيراد التسليم', $this->money((float) ($rev['delivered_revenue'] ?? 0))],
                ['تكلفة WAC (صرف فعلي)', $this->money((float) ($rev['delivered_wac_cost'] ?? 0))],
                ['مجمل الربح', $this->money((float) ($rev['gross_margin'] ?? 0))],
                ['مديونية مدنية عند الصرف', $this->money((float) ($rev['civilian_ar_posted_at_dispense'] ?? 0))],
            ],
        ];
    }

    private function movementReferenceLabel(StockMovement $movement): string
    {
        if ($movement->reference_type === 'bom' && $movement->reference_id) {
            return 'BOM #'.$movement->reference_id;
        }

        return $movement->reference_type ?? '—';
    }

    private function stockActivityStatus(StockItem $item, Carbon $stagnantCutoff): string
    {
        if ($item->status === StockItem::STATUS_LOW) {
            return 'منخفضة';
        }

        $lastMoved = $item->last_moved_at;

        if ($lastMoved === null || $lastMoved->lt($stagnantCutoff)) {
            return 'راكدة';
        }

        return 'شغالة';
    }
}
