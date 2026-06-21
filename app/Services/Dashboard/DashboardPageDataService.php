<?php

namespace App\Services\Dashboard;

use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\MedicalRecord;
use App\Models\MilitaryRank;
use App\Models\PricingRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\User;
use App\Models\VisitType;
use App\Enums\PricingRequestStatus;
use App\Models\ApprovalContract;
use App\Models\MilitaryDebt;
use App\Models\Patient;
use App\Services\AdminCaseTrackingService;
use App\Services\BomService;
use App\Services\DoctorTransferService;
use App\Services\StockCatalogService;

/**
 * يُحمّل بيانات Eloquent لكل صفحة لوحة تحكم — يُستدعى من ShowsDashboardPage.
 */
class DashboardPageDataService
{
    public function resolve(string $dashboardKey, string $page): array
    {
        return match ("{$dashboardKey}.{$page}") {
            'admin.employees'       => $this->adminEmployees(),
            'admin.companies'       => $this->adminCompanies(),
            'admin.military-ranks'  => $this->adminMilitaryRanks(),
            'admin.visit-types'     => $this->adminVisitTypes(),
            'admin.stock-categories'=> $this->adminStockCategories(),
            'admin.catalog'         => $this->adminCatalog(),
            'admin.suppliers'       => $this->adminSuppliers(),
            'admin.pricing'         => $this->adminPricing(),
            'admin.cases'           => $this->adminCases(),
            'admin.contracts'       => $this->contractsPage(isAdmin: true),
            'admin.military-debts'  => $this->adminMilitaryDebts(),
            'reception.appointments'=> $this->receptionAppointments(),
            'reception.patients'    => $this->receptionPatients(),
            'reception.delivery'    => $this->receptionDelivery(),
            'reception.contracts'   => $this->contractsPage(isAdmin: false),
            'doctor.queue'          => $this->doctorQueue(),
            'doctor.diagnosis'      => $this->doctorDiagnosis(),
            'doctor.records'        => $this->doctorRecords(),
            'doctor.transfer'       => $this->doctorTransfers(),
            'spec.orders'           => $this->specOrders(),
            'spec.pricing'          => $this->specPricing(),
            'spec.spec'             => $this->specPreview(),
            'operations.operations' => $this->operationsDesk(),
            'technical.inventory'   => $this->technicalInventory(),
            'technical.bom'         => $this->technicalBom(),
            default                 => [],
        };
    }

    private function adminEmployees(): array
    {
        $employees = User::query()
            ->with('role:id,slug,label_ar')
            ->orderByDesc('id')
            ->get(['id', 'name', 'email', 'role_id', 'status', 'last_login_at']);

        $roles = Role::query()
            ->orderBy('label_ar')
            ->get(['id', 'slug', 'label_ar']);

        $activeCount = $employees->where('status', User::STATUS_ACTIVE)->count();

        $editUser = null;
        if ($editId = request()->integer('edit')) {
            $editUser = $employees->firstWhere('id', $editId)
                ?? User::with('role:id,slug,label_ar')->find($editId);
        }

        return [
            'employees'      => $employees,
            'roles'          => $roles,
            'edit_user'      => $editUser,
            'employee_stats' => [
                ['icon' => '👥', 'label' => 'الموظفون', 'value' => (string) $employees->count(), 'bg' => 'rgba(124,58,237,0.1)'],
                ['icon' => '✅', 'label' => 'نشط', 'value' => (string) $activeCount, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '⏸️', 'label' => 'غير نشط', 'value' => (string) ($employees->count() - $activeCount), 'bg' => 'rgba(100,116,139,0.1)'],
                ['icon' => '🩺', 'label' => 'أطباء', 'value' => (string) $employees->where(fn ($u) => $u->role?->slug === Role::SLUG_DOCTOR)->count(), 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
            ],
        ];
    }

    private function adminCompanies(): array
    {
        $companies = ContractCompany::query()
            ->with('debt')
            ->orderByDesc('id')
            ->get();

        return ['companies' => $companies];
    }

    private function adminMilitaryRanks(): array
    {
        return [
            'military_ranks' => MilitaryRank::query()
                ->orderByDesc('id')
                ->get(),
        ];
    }

    private function adminVisitTypes(): array
    {
        return [
            'visit_types' => VisitType::query()
                ->orderByDesc('id')
                ->get(),
        ];
    }

    private function adminStockCategories(): array
    {
        return [
            'stock_categories' => StockCategory::query()
                ->orderByDesc('id')
                ->get(),
        ];
    }

    private function adminCatalog(): array
    {
        $catalogService = app(StockCatalogService::class);

        return [
            'stock_categories' => StockCategory::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'suppliers' => Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'stock_items' => $catalogService->listForDashboard(),
        ];
    }

    private function adminSuppliers(): array
    {
        return [
            'suppliers' => Supplier::query()
                ->orderByDesc('id')
                ->get(),
        ];
    }

    private function adminPricing(): array
    {
        return [
            'pricing_requests' => PricingRequest::query()
                ->with(['caseRecord:id,case_no,stage_key,manufacturing_stage'])
                ->whereIn('status_key', [
                    PricingRequestStatus::AwaitingAdminApproval->value,
                    PricingRequestStatus::SentToReception->value,
                    PricingRequestStatus::Processing->value,
                    PricingRequestStatus::Insufficient->value,
                ])
                ->orderByDesc('request_date')
                ->orderByDesc('id')
                ->get(),
        ];
    }

    private function adminCases(): array
    {
        $buckets = app(AdminCaseTrackingService::class)->buckets();

        return [
            'admin_case_buckets' => [
                'waiting_return' => $buckets['waiting_return']->all(),
                'in_progress'    => $buckets['in_progress']->all(),
                'delivered'      => $buckets['delivered']->all(),
            ],
            'admin_case_counts' => $buckets['counts'],
        ];
    }

    private function receptionAppointments(): array
    {
        return $this->receptionPatientFormData();
    }

    private function receptionPatientFormData(): array
    {
        return [
            'military_ranks' => MilitaryRank::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'civilian_companies' => ContractCompany::query()
                ->where('is_military', false)
                ->orderBy('name')
                ->get(['id', 'name']),
            'military_companies' => ContractCompany::query()
                ->where('is_military', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'visit_types' => VisitType::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    private function receptionPatients(): array
    {
        return array_merge($this->receptionPatientFormData(), [
            'patients' => Patient::query()
                ->with('contractCompany:id,name')
                ->orderByDesc('id')
                ->limit((int) config('dashboards.table_fetch_limit', 1000))
                ->get(),
        ]);
    }

    private function receptionDelivery(): array
    {
        $cases = CaseRecord::query()
            ->with([
                'patient:id,patient_code,name,patient_qr,patient_type',
                'bom:id,case_id,bom_no,stage,finished_at',
            ])
            ->where('stage_key', CaseRecord::STAGE_READY_DELIVERY)
            ->whereHas('bom', fn ($q) => $q->where('stage', \App\Models\Bom::STAGE_FINISHED))
            ->orderByDesc('updated_at')
            ->get();

        $deliveredCount = CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_DELIVERED)
            ->count();

        return [
            'delivery_cases' => $cases,
            'delivery_stats' => [
                ['key' => 'delivered', 'icon' => '🎉', 'label' => 'تم التسليم', 'value' => (string) $deliveredCount, 'color' => '#047857', 'bg' => 'rgba(5,150,105,0.1)'],
                ['key' => 'ready', 'icon' => '✅', 'label' => 'جاهز للتسليم', 'value' => (string) $cases->count(), 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['key' => 'military', 'icon' => '🪖', 'label' => 'عسكري', 'value' => (string) $cases->filter(fn ($c) => $c->isMilitary())->count(), 'color' => '#4f46e5', 'bg' => 'rgba(79,70,229,0.1)'],
                ['key' => 'civilian', 'icon' => '🌐', 'label' => 'مدني', 'value' => (string) $cases->filter(fn ($c) => ! $c->isMilitary())->count(), 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['key' => 'bom_finished', 'icon' => '📋', 'label' => 'BOM تام', 'value' => (string) $cases->count(), 'bg' => 'rgba(5,150,105,0.1)'],
            ],
        ];
    }

    private function doctorQueue(): array
    {
        $date = request()->query('date', now()->toDateString());

        $baseQuery = Appointment::query()
            ->whereDate('appointment_date', $date)
            ->where('transferred_to_clinic', true);

        $appointments = (clone $baseQuery)
            ->with('patient:id,patient_code,name,patient_type,company_name,created_at')
            ->where('status', Appointment::STATUS_IN_CLINIC)
            ->orderByDesc('transferred_to_clinic_at')
            ->orderByDesc('id')
            ->get();

        $examinedCount = (clone $baseQuery)
            ->where('status', Appointment::STATUS_DONE)
            ->count();

        $waitingCount = $appointments->count();

        return [
            'queue_date'           => $date,
            'queue_appointments'   => $appointments,
            'queue_today_total'    => $waitingCount + $examinedCount,
            'queue_waiting_count'  => $waitingCount,
            'queue_examined_count' => $examinedCount,
        ];
    }

    private function doctorDiagnosis(): array
    {
        $appointment = null;
        $draft       = null;

        if ($id = request()->integer('appointment')) {
            $appointment = Appointment::with('patient')->find($id);

            if ($appointment && $appointment->status !== Appointment::STATUS_IN_CLINIC) {
                $appointment = null;
            }

            if ($appointment) {
                $draft = MedicalRecord::where('appointment_id', $appointment->id)
                    ->where('locked', false)
                    ->first();
            }
        }

        return [
            'selected_appointment' => $appointment,
            'selected_patient'     => $appointment?->patient,
            'draft_record'         => $draft,
        ];
    }

    private function doctorRecords(): array
    {
        $records = MedicalRecord::query()
            ->with(['items', 'patient:id,phone'])
            ->where('locked', true)
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get()
            ->map(fn (MedicalRecord $record) => [
                'id'             => $record->id,
                'patient_id'     => $record->patient_id,
                'appointment_id' => $record->appointment_id,
                'case_id'        => $record->case_id,
                'patient_name'   => $record->patient_name,
                'phone'          => $record->patient?->phone,
                'national_id'    => $record->national_id,
                'company_name'   => $record->company_name,
                'patient_type'   => $record->patient_type,
                'diagnosis'      => $record->diagnosis,
                'prescription'   => $record->prescription,
                'doctor_name'    => $record->doctor_name,
                'record_date'    => $record->record_date?->toDateString(),
                'status'         => $record->status,
                'locked'         => $record->locked,
                'items'          => $record->items->map(fn ($item) => $item->only([
                    'stock_item_code', 'name', 'qty',
                ]))->values(),
            ]);

        return ['medical_records' => $records];
    }

    private function doctorTransfers(): array
    {
        $service = app(DoctorTransferService::class);
        $rows    = $service->list();
        $stats   = $service->stats($rows);

        return [
            'transferred_cases' => $rows,
            'transfer_stats'    => $stats,
        ];
    }

    private function specOrders(): array
    {
        $cases = CaseRecord::query()
            ->with([
                'patient:id,patient_code,name,patient_type,company_name',
                'techOrderSpec:id,case_id,locked,submitted_at',
            ])
            ->where('stage_key', CaseRecord::STAGE_TECHNICAL)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return [
            'spec_cases'  => $cases,
            'spec_stats'  => [
                ['icon' => '📥', 'label' => 'بانتظار التوصيف', 'value' => (string) $cases->count(), 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '📅', 'label' => 'اليوم', 'value' => (string) $cases->where('created_at', '>=', now()->startOfDay())->count(), 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '📊', 'label' => '—', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
                ['icon' => '📊', 'label' => '—', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
            ],
        ];
    }

    private function specPricing(): array
    {
        $requests = PricingRequest::query()
            ->with('caseRecord:id,case_no,order_ref,stage_key,manufacturing_stage')
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $inPricing = $requests->filter(fn ($r) => in_array($r->caseRecord?->stage_key, [CaseRecord::STAGE_COST_CALC, CaseRecord::STAGE_ADMIN_APPROVAL], true))->count();
        $inProduction = $requests->filter(fn ($r) => in_array($r->caseRecord?->stage_key, [CaseRecord::STAGE_MANUFACTURING, CaseRecord::STAGE_READY_DELIVERY], true))->count();

        return [
            'spec_pricing_requests' => $requests,
            'spec_pricing_stats'    => [
                ['icon' => '📋', 'label' => 'طلبات', 'value' => (string) $requests->count(), 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '⏳', 'label' => 'في التسعير / الاعتماد', 'value' => (string) $inPricing, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '🏭', 'label' => 'في الإنتاج', 'value' => (string) $inProduction, 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '🔩', 'label' => 'متوسط البنود', 'value' => $requests->count() ? (string) round($requests->avg('items_count')) : '0', 'bg' => 'rgba(217,119,6,0.1)'],
            ],
        ];
    }

    private function specPreview(): array
    {
        $specs = \App\Models\TechOrderSpec::query()
            ->where('locked', true)
            ->with(['items', 'caseRecord:id,case_no,order_ref'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return ['submitted_specs' => $specs];
    }

    private function operationsDesk(): array
    {
        app(BomService::class)->repairOrphanWipCases();

        $cases = CaseRecord::query()
            ->releasedToWorkshop()
            ->with([
                'patient:id,patient_code,name',
                'bom:id,case_id,bom_no,stage',
                'bom.items:id,bom_id',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $wipCount  = $cases->filter(fn ($c) => $c->bom?->stage === \App\Models\Bom::STAGE_WIP)->count();
        $doneCount = $cases->filter(fn ($c) => $c->bom?->stage === \App\Models\Bom::STAGE_FINISHED)->count();
        $milCount  = $cases->filter(fn ($c) => $c->isMilitary())->count();
        $civCount  = $cases->count() - $milCount;

        return [
            'ops_cases'  => $cases,
            'ops_stats'  => [
                ['icon' => '🎯', 'label' => 'أوامر نشطة', 'value' => (string) $cases->count(), 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '🪖', 'label' => 'مسار عسكري', 'value' => (string) $milCount, 'color' => '#4f46e5', 'bg' => 'rgba(79,70,229,0.1)'],
                ['icon' => '🌐', 'label' => 'مسار مدني', 'value' => (string) $civCount, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '🏭', 'label' => 'تحت التشغيل', 'value' => (string) $wipCount, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
            ],
            'ops_summary' => [
                'raw'  => 0,
                'wip'  => $wipCount,
                'done' => $doneCount,
            ],
        ];
    }

    private function technicalInventory(): array
    {
        $items = StockItem::query()
            ->with('category:id,name')
            ->orderBy('code')
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get();

        $okCount    = $items->where('status', StockItem::STATUS_OK)->count();
        $lowCount   = $items->where('status', StockItem::STATUS_LOW)->count();
        $reserved   = (int) $items->sum('reserved');

        return [
            'inventory_items' => $items->map(fn (StockItem $item) => [
                'id'            => $item->id,
                'code'          => $item->code,
                'name'          => $item->name,
                'spec'          => $item->spec ?? '',
                'category'      => $item->category?->name ?? '',
                'category_id'   => $item->category_id,
                'qty'           => (int) $item->qty,
                'reserved'      => (int) $item->reserved,
                'available'     => $item->availableQty(),
                'status'        => $item->status,
                'barcode'       => $item->barcode,
                'last_moved_at' => $item->last_moved_at?->format('d/m/Y'),
            ])->values()->all(),
            'inventory_suppliers' => Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'inventory_stats' => [
                ['icon' => '💚', 'label' => 'صحة المخزون', 'value' => $this->inventoryHealthLabel($items), 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '✅', 'label' => 'متوفر', 'value' => (string) $okCount, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '⚠️', 'label' => 'منخفض', 'value' => (string) $lowCount, 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
                ['icon' => '🔒', 'label' => 'محجوز', 'value' => (string) $reserved, 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
            ],
        ];
    }

    /** @param \Illuminate\Support\Collection<int, StockItem> $items */
    private function inventoryHealthLabel($items): string
    {
        if ($items->isEmpty()) {
            return '0/100';
        }

        $total      = $items->count();
        $okPct      = (int) round($items->where('status', StockItem::STATUS_OK)->count() / $total * 100);
        $sufficient = $items->filter(fn (StockItem $i) => $i->qty > StockItem::LOW_QTY_THRESHOLD)->count();
        $suffPct    = (int) round($sufficient / $total * 100);
        $coverPct   = min(100, (int) round(($total - $items->where('status', StockItem::STATUS_LOW)->count()) / $total * 100 + 15));
        $score      = (int) round($okPct * 0.4 + $suffPct * 0.35 + $coverPct * 0.25);

        return $score . '/100';
    }

    private function technicalBom(): array
    {
        $boms = \App\Models\Bom::query()
            ->with([
                'caseRecord:id,case_no,work_order_no,patient_type,manufacturing_stage',
                'items:id,bom_id,stock_item_code,name,qty,issued_qty',
            ])
            ->whereHas('caseRecord', fn ($q) => $q->where('stage_key', CaseRecord::STAGE_MANUFACTURING))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $rawCount = $boms->where('stage', \App\Models\Bom::STAGE_RAW)->count();
        $wipCount = $boms->where('stage', \App\Models\Bom::STAGE_WIP)->count();
        $finCount = $boms->where('stage', \App\Models\Bom::STAGE_FINISHED)->count();

        return [
            'warehouse_boms' => $boms,
            'bom_stats'      => [
                ['icon' => '📦', 'label' => 'خام', 'value' => (string) $rawCount, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '🏭', 'label' => 'تحت التشغيل', 'value' => (string) $wipCount, 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '✅', 'label' => 'تام', 'value' => (string) $finCount, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '📋', 'label' => 'إجمالي القوائم', 'value' => (string) $boms->count(), 'bg' => 'rgba(124,58,237,0.1)'],
            ],
        ];
    }

    private function adminMilitaryDebts(): array
    {
        $debts   = MilitaryDebt::query()->orderBy('status')->orderByDesc('delivered_at')->orderByDesc('id')->get();
        $pending   = $debts->where('status', MilitaryDebt::STATUS_PENDING)->count();
        $collected = $debts->where('status', MilitaryDebt::STATUS_COLLECTED)->count();
        $pendingAmt   = $debts->where('status', MilitaryDebt::STATUS_PENDING)->sum(fn ($d) => (float) $d->total_cost);
        $collectedAmt = $debts->where('status', MilitaryDebt::STATUS_COLLECTED)->sum(fn ($d) => (float) $d->total_cost);

        return [
            'military_debts'       => $debts,
            'military_debts_stats' => [
                ['icon' => '📋', 'label' => 'إجمالي السجلات', 'value' => (string) $debts->count(), 'bg' => 'rgba(79,70,229,0.1)', 'color' => '#4f46e5', 'key' => 'total'],
                ['icon' => '🔴', 'label' => 'بانتظار التحصيل', 'value' => (string) $pending, 'bg' => 'rgba(220,38,38,0.1)', 'color' => '#dc2626', 'key' => 'pending_count'],
                ['icon' => '🟢', 'label' => 'تم التحصيل', 'value' => (string) $collected, 'bg' => 'rgba(5,150,105,0.1)', 'color' => '#059669', 'key' => 'collected_count'],
                ['icon' => '💰', 'label' => 'المبالغ المعلقة', 'value' => number_format($pendingAmt, 0), 'bg' => 'rgba(217,119,6,0.1)', 'color' => '#d97706', 'key' => 'pending_amount'],
                ['icon' => '✅', 'label' => 'المبالغ المستحقة', 'value' => number_format($collectedAmt, 0), 'bg' => 'rgba(5,150,105,0.1)', 'color' => '#059669', 'key' => 'collected_amount'],
            ],
        ];
    }

    private function contractsPage(bool $isAdmin): array
    {
        $contracts = ApprovalContract::query()
            ->orderByDesc('approval_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $totalAmount = $contracts->sum(fn ($c) => (float) $c->approved_amount);

        return [
            'contracts'    => $contracts,
            'is_admin'     => $isAdmin,
            'contracts_stats' => [
                ['icon' => '📑', 'label' => 'إجمالي العقود', 'value' => (string) $contracts->count(), 'bg' => 'rgba(124,58,237,0.1)'],
                ['icon' => '💰', 'label' => 'إجمالي المبالغ', 'value' => number_format($totalAmount, 0), 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '📅', 'label' => 'هذا الشهر', 'value' => (string) $contracts->filter(fn ($c) => $c->approval_date?->isCurrentMonth())->count(), 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '📎', 'label' => 'لها مستندات', 'value' => (string) $contracts->filter(fn ($c) => $c->letter_path)->count(), 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
            ],
        ];
    }
}
