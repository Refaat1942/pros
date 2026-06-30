<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Models\Appointment;
use App\Models\ApprovalContract;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Models\DebtCollectionEntry;
use App\Models\Patient;
use App\Models\ReturnNote;
use App\Enums\SpecEditRequestStatus;
use App\Models\SpecEditRequest;
use App\Models\Supplier;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Models\StockMovement;
use App\Models\VisitType;
use App\Support\CaseFinancialSummary;
use App\Support\ClinicTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * مركز التقارير — بطاقات أقسام لوحة الإدارة مع فلترة تاريخية وتصدير.
 */
class AdminReportsHubService
{
    public function __construct(
        private readonly AdminReportsService $snapshotReports,
        private readonly SupplierService $supplierService,
    ) {
    }

    /** @return list<array{id: string, label: string, icon: string, group: string, description: string}> */
    public function sections(): array
    {
        $pages = config('dashboards.admin.pages', []);
        $skip = ['overview', 'bi', 'general-view', 'reports', 'reports-section', 'permissions', 'employees', 'notifications', 'military-ranks', 'military-debts'];

        $cards = [];
        $groups = [
            'patient-tracks'     => 'مسار المرضى والحالات',
            'cases'              => 'مسار المرضى والحالات',
            'spec-edit-requests' => 'مسار المرضى والحالات',
            'visit-types'        => 'مسار المرضى والحالات',
            'catalog'            => 'المخزون والتوريد',
            'inventory-overview' => 'المخزون والتوريد',
            'suppliers'          => 'المخزون والتوريد',
            'returns'            => 'المخزون والتوريد',
            'companies'        => 'التعاقد والمالية',
            'contracts'        => 'التعاقد والمالية',
            'civilian-debts'   => 'التعاقد والمالية',
            'audit'            => 'الرقابة',
            'financial'        => 'رؤية عامة',
            'inventory'        => 'رؤية عامة',
            'operations'       => 'رؤية عامة',
            'bom'              => 'رؤية عامة',
        ];

        $reportLabels = [
            'civilian-debts' => 'المديونات',
        ];

        foreach ($pages as $slug => $meta) {
            if (in_array($slug, $skip, true) || ! empty($meta['hidden'])) {
                continue;
            }

            $cards[] = [
                'id'          => $slug,
                'label'       => $reportLabels[$slug] ?? ($meta['label'] ?? $slug),
                'icon'        => $meta['icon'] ?? '📄',
                'group'       => $groups[$slug] ?? 'أخرى',
                'description' => $meta['title'] ?? '',
            ];
        }

        foreach ([
            ['id' => 'financial', 'label' => 'الإيرادات والمالية', 'icon' => '💰', 'group' => 'رؤية عامة', 'description' => 'إيرادات التسليم وأوامر التشغيل'],
            ['id' => 'inventory', 'label' => 'تحليلات المخزون', 'icon' => '📦', 'group' => 'رؤية عامة', 'description' => 'الأصناف الراكدة والشغالة ومنخفضة المخزون'],
            ['id' => 'operations', 'label' => 'التشغيل والأوامر', 'icon' => '🎯', 'group' => 'رؤية عامة', 'description' => 'أوامر التحضير والورشة'],
            ['id' => 'bom', 'label' => 'قوائم BOM', 'icon' => '📋', 'group' => 'رؤية عامة', 'description' => 'تقييم BOM حسب Highest Batch Cost'],
        ] as $extra) {
            $cards[] = $extra;
        }

        return $cards;
    }

    public function sectionMeta(string $section): ?array
    {
        return collect($this->sections())->firstWhere('id', $section);
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    public function build(string $section, Carbon $from, Carbon $to): array
    {
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $from = $from->copy()->startOfDay();
        $to   = $to->copy()->endOfDay();

        return match ($section) {
            'financial'          => $this->buildFinancial($from, $to),
            'inventory'          => $this->buildInventoryAnalytics($from, $to),
            'operations'         => $this->buildOperations($from, $to),
            'bom'                => $this->buildBom($from, $to),
            'patient-tracks'     => $this->buildPatientTracks($from, $to),
            'cases'              => $this->buildCases($from, $to),
            'spec-edit-requests' => $this->buildSpecEditRequests($from, $to),
            'visit-types'        => $this->buildVisitTypes($from, $to),
            'catalog'            => $this->buildCatalog($from, $to),
            'inventory-overview' => $this->buildInventoryMovements($from, $to),
            'suppliers'          => $this->buildSuppliers($from, $to),
            'returns'            => $this->buildReturns($from, $to),
            'companies'          => $this->buildCompanies($from, $to),
            'contracts'          => $this->buildContracts($from, $to),
            'civilian-debts'     => $this->buildCivilianDebts($from, $to),
            'audit'              => $this->buildAudit($from, $to),
            default              => throw new InvalidArgumentException("تقرير غير معروف: {$section}"),
        };
    }

    /** @return array{from: Carbon, to: Carbon} */
    public function parseDateRange(?string $from, ?string $to): array
    {
        $fromDate = $from ? Carbon::parse($from) : now()->startOfMonth();
        $toDate   = $to ? Carbon::parse($to) : now();

        return [
            'from' => $fromDate->copy()->startOfDay(),
            'to'   => $toDate->copy()->endOfDay(),
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildFinancial(Carbon $from, Carbon $to): array
    {
        $delivered = CaseRecord::query()
            ->with('patient:id,name')
            ->where('patient_type', Patient::TYPE_CIVILIAN)
            ->where('stage_key', CaseRecord::STAGE_DELIVERED)
            ->whereBetween('delivered_at', [$from, $to])
            ->orderByDesc('delivered_at')
            ->get();

        $rows = $delivered->map(fn (CaseRecord $c) => [
            $c->case_no ?? '—',
            $c->patient?->name ?? '—',
            $c->work_order_no ?? '—',
            $c->invoice_no ?? '—',
            number_format(CaseFinancialSummary::totalCost($c), 2) . ' ج.م',
        ])->values()->all();

        return [
            'title'         => 'الإيرادات والمالية',
            'period_label'  => $this->periodLabel($from, $to),
            'summary'       => [],
            'headers'       => ['رقم الحالة', 'المريض', 'أمر التشغيل', 'الفاتورة', 'الإجمالي'],
            'rows'          => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildInventoryAnalytics(Carbon $from, Carbon $to): array
    {
        $stagnantCutoff = $to->copy()->subDays(180)->startOfDay();

        $items = StockItem::query()
            ->orderBy('code')
            ->limit(500)
            ->get();

        $counts = [
            'stagnant' => 0,
            'active'   => 0,
            'low'      => 0,
        ];

        $rows = $items->map(function (StockItem $item) use ($stagnantCutoff, &$counts) {
            $status = $this->stockActivityStatus($item, $stagnantCutoff);
            $counts[$status === 'راكدة' ? 'stagnant' : ($status === 'شغالة' ? 'active' : 'low')]++;

            return [
                $item->code ?? '—',
                $item->name ?? '—',
                (string) ($item->qty ?? 0),
                $item->last_moved_at ? ClinicTime::format($item->last_moved_at, 'd/m/Y') : '—',
                $status,
            ];
        })->values()->all();

        return [
            'title'        => 'تحليلات المخزون',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'أصناف راكدة', 'value' => (string) $counts['stagnant']],
                ['label' => 'أصناف شغالة', 'value' => (string) $counts['active']],
                ['label' => 'أصناف منخفضة', 'value' => (string) $counts['low']],
                ['label' => 'إجمالي الأصناف', 'value' => (string) $items->count()],
            ],
            'headers'      => ['الكود', 'اسم الصنف', 'الكمية', 'آخر حركة', 'الحالة'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildOperations(Carbon $from, Carbon $to): array
    {
        $cases = CaseRecord::query()
            ->with('patient:id,name')
            ->whereNotNull('work_order_no')
            ->whereBetween('updated_at', [$from, $to])
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
            'title'        => 'التشغيل والأوامر',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'أوامر محدّثة في الفترة', 'value' => (string) $cases->count()],
            ],
            'headers'      => ['أمر التشغيل', 'المريض', 'المرحلة', 'آخر تحديث'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildBom(Carbon $from, Carbon $to): array
    {
        $snapshot = $this->snapshotReports->build($from, $to);
        $bomRows  = $snapshot['bom']['rows'] ?? [];

        $rows = collect($bomRows)->map(fn (array $row) => [
            $row['patient'] ?? '—',
            $row['work_order_no'] ?? '—',
            $row['stage_label'] ?? '—',
            (string) ($row['line_count'] ?? 0),
            number_format((float) ($row['value'] ?? 0), 2) . ' ج.م',
        ])->values()->all();

        return [
            'title'        => 'قوائم BOM',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'قوائم BOM', 'value' => (string) count($rows)],
            ],
            'headers'      => ['المريض', 'أمر التشغيل', 'المرحلة', 'البنود', 'قيمة BOM'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildPatientTracks(Carbon $from, Carbon $to): array
    {
        $cases = CaseRecord::query()
            ->with(['patient:id,name,patient_code', 'contractCompany:id,name'])
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $rows = $cases->map(fn (CaseRecord $c) => [
            $c->patient?->name ?? '—',
            $c->case_no ?? '—',
            $c->company_name ?? $c->contractCompany?->name ?? '—',
            CaseStage::labelFor($c->stage_key),
            ClinicTime::format($c->created_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title'        => 'مسار المرضى — حالات جديدة',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'حالات فُتحت', 'value' => (string) $cases->count()],
            ],
            'headers'      => ['المريض', 'رقم الحالة', 'الجهة', 'المرحلة', 'تاريخ الفتح'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildCases(Carbon $from, Carbon $to): array
    {
        $cases = CaseRecord::query()
            ->with('patient:id,name')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('updated_at', [$from, $to])
                    ->orWhereBetween('delivered_at', [$from, $to]);
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
            'title'        => 'متابعة الحالات',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'حالات نشطة/محدّثة', 'value' => (string) $cases->count()],
            ],
            'headers'      => ['رقم الحالة', 'المريض', 'المرحلة', 'أمر التشغيل', 'التاريخ'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildVisitTypes(Carbon $from, Carbon $to): array
    {
        $appointments = Appointment::query()
            ->with('visitTypeRecord:id,name')
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $grouped = $appointments->groupBy(fn (Appointment $a) => $a->displayVisitType());

        $rows = $grouped->map(fn (Collection $group, string $label) => [
            $label,
            (string) $group->count(),
        ])->values()->all();

        return [
            'title'        => 'أنواع الزيارات',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'إجمالي المواعيد', 'value' => (string) $appointments->count()],
                ['label' => 'أنواع مختلفة', 'value' => (string) $grouped->count()],
            ],
            'headers'      => ['نوع الزيارة', 'العدد'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildCatalog(Carbon $from, Carbon $to): array
    {
        $batches = StockItemPrice::query()
            ->with(['stockItem' => fn ($q) => $q->select('id', 'code', 'name')->withCount('prices')])
            ->whereBetween('received_at', [$from, $to])
            ->orderByDesc('received_at')
            ->limit(500)
            ->get();

        $rowActions = [];

        $rows = $batches->map(function (StockItemPrice $p) use (&$rowActions) {
            $priceCount = (int) ($p->stockItem?->prices_count ?? 0);
            $multiPrice = $priceCount > 1;

            $rowActions[] = [
                'stock_item_id' => (int) $p->stock_item_id,
            ];

            return [
                $p->stockItem?->code ?? '—',
                $p->stockItem?->name ?? '—',
                number_format((float) $p->amount, 2) . ' ج.م',
                (string) $p->qty,
                ClinicTime::format($p->received_at, 'd/m/Y'),
                $multiPrice ? ('نعم (' . $priceCount . ' أسعار)') : 'لا',
            ];
        })->values()->all();

        return [
            'title'        => 'الأصناف والأسعار — دفعات جديدة',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'دفعات أسعار', 'value' => (string) $batches->count()],
            ],
            'headers'      => ['الكود', 'الصنف', 'السعر', 'الكمية', 'تاريخ الاستلام', 'أسعار متعددة'],
            'rows'         => $rows,
            'row_actions'  => $rowActions,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildInventoryMovements(Carbon $from, Carbon $to): array
    {
        $movements = StockMovement::query()
            ->with('stockItem:id,code,name')
            ->whereBetween('moved_at', [$from, $to])
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
            'title'        => 'متابعة حركة الأصناف',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'حركات مخزون', 'value' => (string) $movements->count()],
            ],
            'headers'      => ['التاريخ', 'النوع', 'الكود', 'اسم الصنف', 'الكمية'],
            'rows'         => $rows,
        ];
    }

    private function movementTypeLabel(StockMovement $movement): string
    {
        return match ($movement->movement_type) {
            StockMovement::TYPE_ISSUE   => 'صرف / بيع',
            StockMovement::TYPE_RETURN  => 'ارتجاع من الورشة',
            StockMovement::TYPE_RECEIVE => 'توريد',
            default                     => $movement->movement_type ?? '—',
        };
    }

    /** كمية موقّعة للعرض: موجب للصرف، سالب للارتجاع من الورشة. */
    private function signedMovementQuantity(StockMovement $movement): int
    {
        $qty = (int) $movement->quantity;

        return match ($movement->movement_type) {
            StockMovement::TYPE_ISSUE   => abs($qty),
            StockMovement::TYPE_RETURN  => -abs($qty),
            StockMovement::TYPE_RECEIVE => abs($qty),
            default                     => $qty,
        };
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>, row_actions: list<array<string, mixed>>} */
    private function buildReturns(Carbon $from, Carbon $to): array
    {
        $notes = ReturnNote::query()
            ->with('lines')
            ->whereIn('status', [ReturnNote::STATUS_PARTIAL, ReturnNote::STATUS_COMPLETED])
            ->whereHas('lines', fn ($q) => $q->where('qty_returned', '>', 0))
            ->whereBetween('updated_at', [$from, $to])
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $rowActions = $notes->map(function (ReturnNote $n) {
            $receivedLines = $n->lines
                ->filter(fn ($line) => (int) $line->qty_returned > 0)
                ->values();

            return [
                'return_no'      => $n->return_no ?? '—',
                'patient_name'   => $n->patient_name ?? '—',
                'work_order_no'  => $n->work_order_no ?? '—',
                'warehouse_received_at' => ClinicTime::format($n->completed_at ?? $n->updated_at, 'd/m/Y H:i'),
                'can_view_items' => $receivedLines->isNotEmpty(),
                'lines'          => $receivedLines->map(fn ($line) => [
                    'code'         => $line->stock_item_code,
                    'name'         => $line->name ?: $line->stock_item_code,
                    'qty_returned' => (int) $line->qty_returned,
                    'reason'       => $line->reason ?? '—',
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
            'title'        => 'طلبات الارتجاع',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'طلبات مُستلَمة من المخزن', 'value' => (string) $notes->count()],
            ],
            'headers'      => ['رقم الطلب', 'المريض', 'أمر التشغيل', 'البنود', 'تاريخ الاستلام'],
            'rows'         => $rows,
            'row_actions'  => $rowActions,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildSpecEditRequests(Carbon $from, Carbon $to): array
    {
        $requests = SpecEditRequest::query()
            ->with([
                'techOrderSpec:id,order_ref,patient_name',
                'caseRecord:id,case_no,order_ref',
                'requestedBy:id,name',
            ])
            ->whereBetween('created_at', [$from, $to])
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

        $pending = $requests->filter(
            fn (SpecEditRequest $r) => $r->status === SpecEditRequestStatus::Pending
        )->count();

        return [
            'title'        => 'طلبات تعديل التوصيف',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'طلبات', 'value' => (string) $requests->count()],
                ['label' => 'معلّقة', 'value' => (string) $pending],
            ],
            'headers'      => ['رقم الحالة', 'المريض', 'مرجع الطلب', 'الحالة', 'طلب بواسطة', 'البنود', 'التاريخ'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildSuppliers(Carbon $from, Carbon $to): array
    {
        $suppliers = $this->supplierService->listForAdmin(
            null,
            $from->toDateString(),
            $to->toDateString(),
        );

        $rows = $suppliers->map(fn (Supplier $s) => [
            $s->name ?? '—',
            $s->phone ?? '—',
            $s->email ?? '—',
            (string) ($s->linked_items_count ?? 0),
            number_format((float) ($s->debt_total ?? 0), 2) . ' ج.م',
            ClinicTime::format($s->created_at, 'd/m/Y'),
        ])->values()->all();

        $withDebt = $suppliers->filter(fn (Supplier $s) => ($s->debt_total ?? 0) > 0)->count();

        return [
            'title'        => 'الموردون',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'موردون', 'value' => (string) $suppliers->count()],
                ['label' => 'عليهم مديونية', 'value' => (string) $withDebt],
            ],
            'headers'      => ['المورد', 'الهاتف', 'البريد', 'أصناف مرتبطة', 'المديونية', 'تاريخ الإضافة'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildCompanies(Carbon $from, Carbon $to): array
    {
        $companies = ContractCompany::query()
            ->whereBetween('created_at', [$from, $to])
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
            'title'        => 'جهات التعاقد',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'جهات أُضيفت', 'value' => (string) $companies->count()],
            ],
            'headers'      => ['الكود', 'الاسم', 'النوع', 'الجهة', 'التصنيف'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildContracts(Carbon $from, Carbon $to): array
    {
        $contracts = ApprovalContract::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('approval_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $rows = $contracts->map(fn (ApprovalContract $c) => [
            $c->contract_no ?? '—',
            $c->patient_name ?? '—',
            $c->company_name ?? '—',
            number_format((float) $c->approved_amount, 2) . ' ج.م',
            ClinicTime::format($c->approval_date ?? $c->created_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title'        => 'موافقات جهات التعاقد',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'موافقات', 'value' => (string) $contracts->count()],
                ['label' => 'إجمالي معتمد', 'value' => number_format($contracts->sum('approved_amount'), 2) . ' ج.م'],
            ],
            'headers'      => ['رقم العقد', 'المريض', 'الجهة', 'المبلغ', 'التاريخ'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildCivilianDebts(Carbon $from, Carbon $to): array
    {
        $entries = DebtCollectionEntry::query()
            ->with(['payable' => fn ($q) => $q->with('contractCompany:id,name,company_code')])
            ->where('payable_type', ContractCompanyDebt::class)
            ->whereBetween('collected_at', [$from, $to])
            ->orderByDesc('collected_at')
            ->limit(500)
            ->get();

        $rows = $entries->map(function (DebtCollectionEntry $e) {
            $debt = $e->payable instanceof ContractCompanyDebt ? $e->payable : null;
            $company = $debt?->contractCompany;

            return [
                ClinicTime::format($e->collected_at, 'd/m/Y'),
                $company?->name ?? '—',
                number_format((float) $e->amount, 2) . ' ج.م',
            ];
        })->values()->all();

        return [
            'title'        => 'المديونات',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'عمليات تحصيل', 'value' => (string) $entries->count()],
                ['label' => 'إجمالي محصّل', 'value' => number_format($entries->sum('amount'), 2) . ' ج.م'],
            ],
            'headers'      => ['التاريخ', 'الجهة', 'المبلغ'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildAudit(Carbon $from, Carbon $to): array
    {
        $logs = AuditLog::query()
            ->whereBetween('logged_at', [$from, $to])
            ->orderByDesc('logged_at')
            ->limit(500)
            ->get();

        $rows = $logs->map(fn (AuditLog $log) => [
            ClinicTime::format($log->logged_at, 'd/m/Y H:i'),
            $log->user_name ?? '—',
            $log->action ?? '—',
            $log->tag ?? '—',
            \Illuminate\Support\Str::limit($log->description ?? '—', 80),
        ])->values()->all();

        return [
            'title'        => 'سجل الرقابة',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'عمليات مسجّلة', 'value' => (string) $logs->count()],
            ],
            'headers'      => ['التاريخ', 'المستخدم', 'الإجراء', 'الوسم', 'الوصف'],
            'rows'         => $rows,
        ];
    }

    private function periodLabel(Carbon $from, Carbon $to): string
    {
        return ClinicTime::format($from, 'd/m/Y') . ' — ' . ClinicTime::format($to, 'd/m/Y');
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
