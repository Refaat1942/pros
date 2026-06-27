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
use App\Models\MilitaryDebt;
use App\Models\Patient;
use App\Models\ReturnNote;
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
    public function __construct(private readonly AdminReportsService $snapshotReports)
    {
    }

    /** @return list<array{id: string, label: string, icon: string, group: string, description: string}> */
    public function sections(): array
    {
        $pages = config('dashboards.admin.pages', []);
        $skip = ['overview', 'bi', 'general-view', 'reports', 'reports-section', 'permissions', 'employees', 'notifications', 'military-ranks'];

        $cards = [];
        $groups = [
            'patient-tracks'   => 'مسار المرضى والحالات',
            'cases'            => 'مسار المرضى والحالات',
            'visit-types'      => 'مسار المرضى والحالات',
            'catalog'          => 'المخزون والتوريد',
            'inventory-overview' => 'المخزون والتوريد',
            'returns'          => 'المخزون والتوريد',
            'companies'        => 'التعاقد والمالية',
            'contracts'        => 'التعاقد والمالية',
            'civilian-debts'   => 'التعاقد والمالية',
            'military-debts'   => 'التعاقد والمالية',
            'audit'            => 'الرقابة',
            'financial'        => 'رؤية عامة',
            'inventory'        => 'رؤية عامة',
            'operations'       => 'رؤية عامة',
            'bom'              => 'رؤية عامة',
        ];

        foreach ($pages as $slug => $meta) {
            if (in_array($slug, $skip, true) || ! empty($meta['hidden'])) {
                continue;
            }

            $cards[] = [
                'id'          => $slug,
                'label'       => $meta['label'] ?? $slug,
                'icon'        => $meta['icon'] ?? '📄',
                'group'       => $groups[$slug] ?? 'أخرى',
                'description' => $meta['title'] ?? '',
            ];
        }

        foreach ([
            ['id' => 'financial', 'label' => 'الإيرادات والمالية', 'icon' => '💰', 'group' => 'رؤية عامة', 'description' => 'إيرادات التسليم وأوامر التشغيل'],
            ['id' => 'inventory', 'label' => 'تحليلات المخزون', 'icon' => '📦', 'group' => 'رؤية عامة', 'description' => 'صرف وحركات المخزون'],
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
            'visit-types'        => $this->buildVisitTypes($from, $to),
            'catalog'            => $this->buildCatalog($from, $to),
            'inventory-overview' => $this->buildInventoryMovements($from, $to),
            'returns'            => $this->buildReturns($from, $to),
            'companies'          => $this->buildCompanies($from, $to),
            'contracts'          => $this->buildContracts($from, $to),
            'civilian-debts'     => $this->buildCivilianDebts($from, $to),
            'military-debts'     => $this->buildMilitaryDebts($from, $to),
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

        $revenue = $delivered->sum(fn (CaseRecord $c) => CaseFinancialSummary::totalCost($c));

        $workOrders = CaseRecord::query()
            ->with('patient:id,name')
            ->whereNotNull('work_order_no')
            ->whereBetween('updated_at', [$from, $to])
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $rows = $delivered->map(fn (CaseRecord $c) => [
            $c->case_no ?? '—',
            $c->patient?->name ?? '—',
            $c->work_order_no ?? '—',
            ClinicTime::format($c->delivered_at, 'd/m/Y'),
            number_format(CaseFinancialSummary::totalCost($c), 2) . ' ج.م',
        ])->values()->all();

        return [
            'title'         => 'الإيرادات والمالية',
            'period_label'  => $this->periodLabel($from, $to),
            'summary'       => [
                ['label' => 'إيرادات التسليم', 'value' => number_format($revenue, 2) . ' ج.م'],
                ['label' => 'حالات مُسلَّمة', 'value' => (string) $delivered->count()],
                ['label' => 'أوامر تشغيل محدّثة', 'value' => (string) $workOrders->count()],
            ],
            'headers'       => ['رقم الحالة', 'المريض', 'أمر التشغيل', 'تاريخ التسليم', 'الإجمالي'],
            'rows'          => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildInventoryAnalytics(Carbon $from, Carbon $to): array
    {
        $issues = StockMovement::query()
            ->with('stockItem:id,code,name')
            ->where('movement_type', StockMovement::TYPE_ISSUE)
            ->whereBetween('moved_at', [$from, $to])
            ->orderByDesc('moved_at')
            ->limit(500)
            ->get();

        $totalQty = (int) $issues->sum('quantity');

        $rows = $issues->map(fn (StockMovement $m) => [
            ClinicTime::format($m->moved_at, 'd/m/Y H:i'),
            $m->stockItem?->code ?? '—',
            $m->stockItem?->name ?? '—',
            (string) $m->quantity,
            $m->reference ?? '—',
        ])->values()->all();

        return [
            'title'        => 'تحليلات المخزون — الصرف',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'حركات صرف', 'value' => (string) $issues->count()],
                ['label' => 'إجمالي الكميات المصروفة', 'value' => number_format($totalQty)],
            ],
            'headers'      => ['التاريخ', 'الكود', 'الصنف', 'الكمية', 'المرجع'],
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
            ->with('stockItem:id,code,name')
            ->whereBetween('received_at', [$from, $to])
            ->orderByDesc('received_at')
            ->limit(500)
            ->get();

        $rows = $batches->map(fn (StockItemPrice $p) => [
            $p->stockItem?->code ?? '—',
            $p->stockItem?->name ?? '—',
            number_format((float) $p->amount, 2) . ' ج.م',
            (string) $p->qty,
            ClinicTime::format($p->received_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title'        => 'الأصناف والأسعار — دفعات جديدة',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'دفعات أسعار', 'value' => (string) $batches->count()],
            ],
            'headers'      => ['الكود', 'الصنف', 'السعر', 'الكمية', 'تاريخ الاستلام'],
            'rows'         => $rows,
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
            $m->movement_type ?? '—',
            $m->stockItem?->code ?? '—',
            (string) $m->quantity,
            $m->reference ?? '—',
        ])->values()->all();

        return [
            'title'        => 'المخزون التفصيلي — الحركات',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'حركات مخزون', 'value' => (string) $movements->count()],
            ],
            'headers'      => ['التاريخ', 'النوع', 'الكود', 'الكمية', 'المرجع'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildReturns(Carbon $from, Carbon $to): array
    {
        $notes = ReturnNote::query()
            ->with('lines')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $rows = $notes->map(fn (ReturnNote $n) => [
            $n->return_no ?? '—',
            $n->patient_name ?? '—',
            $n->work_order_no ?? '—',
            (string) ($n->lines?->count() ?? 0),
            ClinicTime::format($n->created_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title'        => 'طلبات الارتجاع',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'طلبات ارتجاع', 'value' => (string) $notes->count()],
            ],
            'headers'      => ['رقم الطلب', 'المريض', 'أمر التشغيل', 'البنود', 'التاريخ'],
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
            $c->is_military ? 'عسكري' : 'مدني',
            ClinicTime::format($c->created_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title'        => 'جهات التعاقد — جديدة',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'جهات أُضيفت', 'value' => (string) $companies->count()],
            ],
            'headers'      => ['الكود', 'الاسم', 'النوع', 'تاريخ الإضافة'],
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
                $e->recorded_by_name ?? '—',
            ];
        })->values()->all();

        return [
            'title'        => 'مديونيات مدنية — تحصيلات',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'عمليات تحصيل', 'value' => (string) $entries->count()],
                ['label' => 'إجمالي محصّل', 'value' => number_format($entries->sum('amount'), 2) . ' ج.م'],
            ],
            'headers'      => ['التاريخ', 'الجهة', 'المبلغ', 'سجّله'],
            'rows'         => $rows,
        ];
    }

    /** @return array{title: string, period_label: string, summary: list<array{label: string, value: string}>, headers: list<string>, rows: list<list<string>>} */
    private function buildMilitaryDebts(Carbon $from, Carbon $to): array
    {
        $debts = MilitaryDebt::query()
            ->with('caseRecord:id,case_no')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $rows = $debts->map(fn (MilitaryDebt $d) => [
            $d->patient_name ?? '—',
            $d->caseRecord?->case_no ?? $d->work_order_no ?? '—',
            number_format((float) $d->total_cost, 2) . ' ج.م',
            $d->status ?? '—',
            ClinicTime::format($d->created_at, 'd/m/Y'),
        ])->values()->all();

        return [
            'title'        => 'مديونيات عسكرية',
            'period_label' => $this->periodLabel($from, $to),
            'summary'      => [
                ['label' => 'سجلات دين', 'value' => (string) $debts->count()],
                ['label' => 'إجمالي', 'value' => number_format($debts->sum('total_cost'), 2) . ' ج.م'],
            ],
            'headers'      => ['المريض', 'رقم الحالة', 'المبلغ', 'الحالة', 'التاريخ'],
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
}
