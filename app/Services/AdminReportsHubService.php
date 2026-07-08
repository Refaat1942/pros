<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Models\ApprovalContract;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Models\DebtCollectionEntry;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ReturnNote;
use App\Models\SpecEditRequest;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Models\StockMovement;
use App\Models\Supplier;
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
    ) {}

    /** @return list<array{id: string, label: string, icon: string, group: string, description: string}> */
    public function sections(): array
    {
        $pages = config('dashboards.admin.pages', []);
        $skip = ['overview', 'bi', 'general-view', 'reports', 'reports-section', 'permissions', 'employees', 'notifications', 'military-ranks', 'military-debts', 'costing-settings'];

        $cards = [];
        $groups = [
            'patient-tracks' => 'مسار المرضى والحالات',
            'cases' => 'مسار المرضى والحالات',
            'spec-edit-requests' => 'مسار المرضى والحالات',
            'visit-types' => 'مسار المرضى والحالات',
            'catalog' => 'المخزون والتوريد',
            'stock-categories' => 'المخزون والتوريد',
            'inventory-overview' => 'المخزون والتوريد',
            'suppliers' => 'المخزون والتوريد',
            'returns' => 'المخزون والتوريد',
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
            'suppliers' => $this->buildSuppliers($from, $to),
            'returns' => $this->buildReturns($from, $to),
            'companies' => $this->buildCompanies($from, $to),
            'contracts' => $this->buildContracts($from, $to),
            'civilian-debts' => $this->buildCivilianDebts($from, $to),
            'audit' => $this->buildAudit($from, $to),
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
        ])->values()->all();

        return [
            'title' => 'الإيرادات والمالية',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['رقم الحالة', 'المريض', 'أمر التشغيل', 'الفاتورة', 'الإجمالي'],
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
            'headers' => ['الكود', 'اسم الصنف', 'الكمية', 'آخر حركة', 'الحالة'],
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
            'headers' => ['الكود', 'الصنف', 'السعر', 'الكمية', 'تاريخ الاستلام', 'أسعار متعددة'],
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
        ])->values()->all();

        return [
            'title' => 'متابعة حركة الأصناف',
            'period_label' => $this->periodLabel($from, $to),
            'summary' => [],
            'headers' => ['التاريخ', 'النوع', 'الكود', 'اسم الصنف', 'الكمية'],
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
