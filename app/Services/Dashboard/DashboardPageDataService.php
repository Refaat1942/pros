<?php

namespace App\Services\Dashboard;

use App\Enums\PaymentMethod;
use App\Enums\SpecEditRequestStatus;
use App\Enums\StockWarehouseType;
use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\ApprovalContract;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\MedicalRecord;
use App\Models\MilitaryDebt;
use App\Models\MilitaryRank;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PricingRequest;
use App\Models\ReturnNote;
use App\Models\ReturnNoteLine;
use App\Models\Role;
use App\Models\SpecEditRequest;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TechOrderSpec;
use App\Models\User;
use App\Models\VisitType;
use App\Services\AdjustmentsTransferHistoryService;
use App\Services\AdminCaseTrackingService;
use App\Services\AdminCivilianDebtService;
use App\Services\AdminPatientTrackService;
use App\Services\AdminReportsHubService;
use App\Services\AdminReportsService;
use App\Services\BomService;
use App\Services\CostingModeService;
use App\Services\DoctorTransferService;
use App\Services\MilitaryDebtService;
use App\Services\Notifications\NotificationService;
use App\Services\PermissionCatalogService;
use App\Services\ReceptionAnalyticsService;
use App\Services\SettingService;
use App\Services\SpecEditRequestService;
use App\Services\SpecOrdersService;
use App\Services\StockCatalogService;
use App\Services\StockCategorySchemaService;
use App\Services\StockPriceService;
use App\Services\SupplierService;
use App\Services\WorkshopAnalyticsService;
use App\Support\ClinicTime;
use Carbon\Carbon;

/**
 * يُحمّل بيانات Eloquent لكل صفحة لوحة تحكم — يُستدعى من ShowsDashboardPage.
 */
class DashboardPageDataService
{
    public function resolve(string $dashboardKey, string $page): array
    {
        if ($page === 'notifications') {
            return $this->notificationsInbox();
        }

        return match ("{$dashboardKey}.{$page}") {
            'admin.employees' => $this->adminEmployees(),
            'admin.companies' => $this->adminCompanies(),
            'admin.military-ranks' => $this->adminMilitaryRanks(),
            'admin.visit-types' => $this->adminVisitTypes(),
            'admin.costing-settings' => $this->adminCostingSettings(),
            'admin.stock-categories' => $this->adminStockCategories(),
            'admin.catalog' => $this->adminCatalog(),
            'admin.inventory-overview' => $this->adminInventoryOverview(),
            'admin.general-view' => $this->adminGeneralView(),
            'admin.reports' => $this->adminReportsHub(),
            'admin.permissions' => $this->adminPermissions(),
            'admin.suppliers' => $this->adminSuppliers(),
            'admin.cases' => $this->adminCases(),
            'admin.patient-tracks' => $this->adminPatientTracks(),
            'admin.spec-edit-requests' => $this->adminSpecEditRequests(),
            'admin.contracts' => $this->contractsPage(isAdmin: true),
            'admin.civilian-debts' => $this->adminCivilianDebts(),
            'admin.military-debts' => $this->adminMilitaryDebts(),
            'admin.returns' => $this->adminReturns(),
            'reception.appointments' => $this->receptionAppointments(),
            'reception.statistics' => $this->receptionStatistics(),
            'reception.patients' => $this->receptionPatients(),
            'reception.delivery' => $this->receptionDelivery(),
            'reception.contracts' => $this->contractsPage(isAdmin: false),
            'doctor.queue' => $this->doctorQueue(),
            'doctor.records' => $this->doctorRecords(),
            'doctor.transfer' => $this->doctorTransfers(),
            'spec.orders' => $this->specOrders(),
            'spec.pricing' => $this->specPricing(),
            'spec.spec' => $this->specPreview(),
            'adjustments.adjustments' => $this->adjustmentsHistory(),
            'adjustments.history' => $this->adjustmentsHistory(),
            'technical.delivery' => $this->operationsDeliveryDesk(),
            'cashier.payments' => $this->cashierPayments(),
            'cashier.statistics' => $this->cashierStatistics(),
            'workshop.workshop' => $this->workshopDesk(),
            'workshop.statistics' => $this->workshopStatistics(),
            'technical.inventory' => $this->technicalInventory(),
            'technical.bom' => $this->technicalBom(),
            default => [],
        };
    }

    private function adminEmployees(): array
    {
        $employees = User::query()
            ->with('role:id,slug,label_ar')
            ->orderByDesc('id')
            ->get(['id', 'name', 'username', 'role_id', 'status', 'last_login_at']);

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
            'employees' => $employees,
            'roles' => $roles,
            'edit_user' => $editUser,
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
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ];
    }

    private function adminVisitTypes(): array
    {
        return [
            'visit_types' => VisitType::query()
                ->ordered()
                ->get(),
        ];
    }

    private function adminCostingSettings(): array
    {
        $settings = app(SettingService::class);

        return [
            'overhead_rate_definitions' => $settings->overheadRateDefinitions(),
            'overhead_rates_sum' => $settings->overheadRatesSum(),
            'costing_modes' => app(CostingModeService::class)->allModes(),
        ];
    }

    private function adminStockCategories(): array
    {
        $schema = app(StockCategorySchemaService::class);

        return [
            'stock_categories' => StockCategory::query()
                ->with('fields')
                ->orderByDesc('id')
                ->get()
                ->map(fn (StockCategory $c) => $schema->formatCategory($c)),
        ];
    }

    private function adminCatalog(): array
    {
        $catalogService = app(StockCatalogService::class);
        $schema = app(StockCategorySchemaService::class);
        $from = request()->query('from');
        $to = request()->query('to');

        return [
            'stock_categories' => StockCategory::query()
                ->with('fields')
                ->orderBy('name')
                ->get()
                ->map(fn (StockCategory $c) => $schema->formatCategory($c)),
            'suppliers' => Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'stock_items' => $catalogService->listForDashboard($from, $to),
            'date_from' => $from,
            'date_to' => $to,
        ];
    }

    private function adminInventoryOverview(): array
    {
        $priceService = app(StockPriceService::class);

        $items = StockItem::query()
            ->with([
                'category:id,name',
                'prices' => fn ($q) => $q->orderByDesc('received_at')->orderByDesc('id'),
                'attributeValues.field',
            ])
            ->orderBy('code')
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get();

        $totalValue = $items->sum(
            fn (StockItem $i) => max(0, (int) $i->qty) * $priceService->wacUnitPrice($i->code)
        );

        $backorderCount = $items->filter(fn (StockItem $i) => $i->isBackorder())->count();

        return [
            'inventory_items' => $items,
            'inventory_overview_stats' => [
                ['icon' => '📦', 'label' => 'إجمالي الأصناف', 'value' => (string) $items->count(), 'bg' => 'rgba(37,99,235,0.1)'],
                ['icon' => '🔻', 'label' => 'أصناف منخفضة', 'value' => (string) $items->where('status', StockItem::STATUS_LOW)->count(), 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
                ['icon' => '🛒', 'label' => 'طلبات توريد', 'value' => (string) $backorderCount, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.12)'],
                ['icon' => '💰', 'label' => 'قيمة المخزون', 'value' => number_format($totalValue, 2), 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
            ],
        ];
    }

    /**
     * مصفوفة الصلاحيات — الأدوار (عدا الأدمن) × الصلاحيات.
     */
    private function adminPermissions(): array
    {
        return app(PermissionCatalogService::class)->matrixPageData();
    }

    private function adminSuppliers(): array
    {
        $service = app(SupplierService::class);

        return [
            'suppliers' => $service->listForAdmin(
                search: request()->query('search'),
                from: request()->query('from'),
                to: request()->query('to'),
                debtFilter: request()->query('debt'),
            ),
            'supplier_filters' => [
                'search' => request()->query('search'),
                'from' => request()->query('from'),
                'to' => request()->query('to'),
                'debt' => request()->query('debt'),
            ],
        ];
    }

    private function adminCases(): array
    {
        $from = request()->query('from')
            ? Carbon::parse((string) request()->query('from'))->startOfDay()
            : now()->startOfMonth();
        $to = request()->query('to')
            ? Carbon::parse((string) request()->query('to'))->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $buckets = app(AdminCaseTrackingService::class)->buckets($from, $to);

        return [
            'admin_case_buckets' => [
                'waiting_return' => $buckets['waiting_return']->all(),
                'awaiting_cashier' => $buckets['awaiting_cashier']->all(),
                'in_progress' => $buckets['in_progress']->all(),
                'delivered' => $buckets['delivered']->all(),
            ],
            'admin_case_counts' => $buckets['counts'],
            'case_date_from' => $from->toDateString(),
            'case_date_to' => $to->toDateString(),
        ];
    }

    private function adminGeneralView(): array
    {
        return [
            'admin_reports' => app(AdminReportsService::class)->build(),
        ];
    }

    private function adminReportsHub(): array
    {
        return [
            'report_sections' => app(AdminReportsHubService::class)->sections(),
        ];
    }

    private function adminPatientTracks(): array
    {
        $tracks = app(AdminPatientTrackService::class)->list(
            search: request()->query('search'),
            stage: request()->query('stage'),
            patientType: request()->query('patient_type'),
            visitType: request()->query('visit_type'),
        );

        return [
            'patient_tracks' => $tracks,
            'track_search' => request()->query('search', ''),
            'track_stage' => request()->query('stage', ''),
            'track_patient_type' => request()->query('patient_type', ''),
            'track_visit_type' => request()->query('visit_type', ''),
            'track_stage_options' => AdminPatientTrackService::stageFilterOptions(),
            'track_visit_options' => AdminPatientTrackService::visitFilterOptions(),
        ];
    }

    private function receptionAppointments(): array
    {
        return $this->receptionPatientFormData();
    }

    private function receptionStatistics(): array
    {
        return [
            'reception_analytics' => app(ReceptionAnalyticsService::class)->build(),
        ];
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
                ->orderByDesc('is_contracted')
                ->orderBy('name')
                ->get(['id', 'name', 'is_contracted']),
            'military_companies' => ContractCompany::query()
                ->where('is_military', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'visit_types' => VisitType::query()
                ->ordered()
                ->get(['id', 'name']),
        ];
    }

    private function receptionPatients(): array
    {
        return array_merge($this->receptionPatientFormData(), [
            'patients' => Patient::query()
                ->with('contractCompany:id,name,is_contracted')
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
            ->whereHas('bom', fn ($q) => $q->where('stage', Bom::STAGE_FINISHED))
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
        $date = request()->query('date', ClinicTime::todayDateString());

        $baseQuery = Appointment::query()
            ->whereDate('appointment_date', $date)
            ->where('transferred_to_clinic', true);

        $appointments = (clone $baseQuery)
            ->with('patient:id,patient_code,name,patient_type,company_name,sovereign_entity,contract_company_id,created_at')
            ->with('patient.contractCompany:id,name,is_contracted')
            ->where('status', Appointment::STATUS_IN_CLINIC)
            ->orderByDesc('transferred_to_clinic_at')
            ->orderByDesc('id')
            ->get();

        $examinedCount = (clone $baseQuery)
            ->where('status', Appointment::STATUS_DONE)
            ->count();

        $waitingCount = $appointments->count();
        $receptionPendingCount = app(DashboardQueueService::class)
            ->doctorReceptionPendingCount($date);

        return [
            'queue_date' => $date,
            'queue_appointments' => $appointments,
            'queue_today_total' => $waitingCount + $examinedCount,
            'queue_waiting_count' => $waitingCount,
            'queue_examined_count' => $examinedCount,
            'queue_reception_pending_count' => $receptionPendingCount,
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
                'id' => $record->id,
                'patient_id' => $record->patient_id,
                'appointment_id' => $record->appointment_id,
                'case_id' => $record->case_id,
                'patient_name' => $record->patient_name,
                'phone' => $record->patient?->phone,
                'national_id' => $record->national_id,
                'company_name' => $record->company_name,
                'display_entity' => $record->displayEntity(),
                'patient_type' => $record->patient_type,
                'diagnosis' => $record->diagnosis,
                'prescription' => $record->prescription,
                'doctor_name' => $record->doctor_name,
                'record_date' => $record->record_date?->toDateString(),
                'status' => $record->status,
                'locked' => $record->locked,
                'items' => $record->items->map(fn ($item) => $item->only([
                    'stock_item_code', 'name', 'qty',
                ]))->values(),
            ]);

        return ['medical_records' => $records];
    }

    private function doctorTransfers(): array
    {
        $service = app(DoctorTransferService::class);
        $rows = $service->list();
        $stats = $service->stats($rows);

        return [
            'transferred_cases' => $rows,
            'transfer_stats' => $stats,
        ];
    }

    private function specOrders(): array
    {
        $service = app(SpecOrdersService::class);
        $range = $service->parseDateRange(request()->query('from'), request()->query('to'));
        $from = $range['from'] ?? null;
        $to = $range['to'] ?? null;
        $search = request()->query('search');
        $cases = $service->list($from, $to, $search);
        $stats = $service->stats($from, $to, $search);

        return [
            'spec_cases' => $cases,
            'spec_orders_date_from' => $from?->toDateString(),
            'spec_orders_date_to' => $to?->toDateString(),
            'spec_orders_search' => $search,
            'spec_orders_export' => $cases->map(fn (CaseRecord $c) => $service->exportRow($c))->values(),
            'spec_stats' => [
                ['key' => 'today_from_doctor', 'icon' => '📅', 'label' => 'إجمالي المحولون من الطبيب اليوم', 'value' => (string) $stats['today_from_doctor'], 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['key' => 'pending_spec', 'icon' => '📥', 'label' => 'بانتظار التوصيف', 'value' => (string) $stats['pending_spec'], 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
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

        $inPricing = $requests->filter(fn ($r) => in_array($r->caseRecord?->stage_key, [CaseRecord::STAGE_COST_CALC, CaseRecord::STAGE_QUOTE, CaseRecord::STAGE_OPERATIONS], true))->count();
        $inProduction = $requests->filter(fn ($r) => in_array($r->caseRecord?->stage_key, [CaseRecord::STAGE_MANUFACTURING, CaseRecord::STAGE_READY_DELIVERY], true))->count();

        return [
            'spec_pricing_requests' => $requests,
            'spec_pricing_stats' => [
                ['icon' => '📋', 'label' => 'طلبات', 'value' => (string) $requests->count(), 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '⏳', 'label' => 'في التسعير / الاعتماد', 'value' => (string) $inPricing, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '🏭', 'label' => 'في الإنتاج', 'value' => (string) $inProduction, 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '🔩', 'label' => 'متوسط البنود', 'value' => $requests->count() ? (string) round($requests->avg('items_count')) : '0', 'bg' => 'rgba(217,119,6,0.1)'],
            ],
        ];
    }

    private function specPreview(): array
    {
        $specs = TechOrderSpec::query()
            ->where('locked', true)
            ->with([
                'items',
                'caseRecord:id,case_no,order_ref,stage_key',
                'pendingEditRequest',
                'rejectedSpecEditRequest',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $editService = app(SpecEditRequestService::class);
        $rejectedCount = $specs->filter(fn ($s) => $s->rejectedSpecEditRequest && ! $s->pendingEditRequest)->count();

        return [
            'submitted_specs' => $specs,
            'spec_edit_stats' => [
                'total' => $specs->count(),
                'editable' => $specs->filter(fn ($s) => $editService->canRequestEdit($s))->count(),
                'pending' => $specs->filter(fn ($s) => $s->pendingEditRequest)->count(),
                'rejected' => $rejectedCount,
            ],
            'spec_preview_stats' => [
                ['icon' => '📋', 'label' => 'توصيفات مُرسَلة', 'value' => (string) $specs->count(), 'color' => '#475569', 'bg' => 'rgba(100,116,139,0.1)'],
                ['icon' => '✏️', 'label' => 'قابل للتعديل', 'value' => (string) $specs->filter(fn ($s) => $editService->canRequestEdit($s))->count(), 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
                ['icon' => '⏳', 'label' => 'طلب معلّق', 'value' => (string) $specs->filter(fn ($s) => $s->pendingEditRequest)->count(), 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '❌', 'label' => 'طلب مرفوض', 'value' => (string) $rejectedCount, 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
            ],
        ];
    }

    private function adminSpecEditRequests(): array
    {
        $service = app(SpecEditRequestService::class);
        $rows = $service->listForAdmin('pending');

        $pending = $service->pendingCount();
        $approved = SpecEditRequest::where('status', SpecEditRequestStatus::Approved)->count();
        $rejected = SpecEditRequest::where('status', SpecEditRequestStatus::Rejected)->count();

        return [
            'spec_edit_requests' => $rows,
            'spec_edit_pending' => $pending,
            'spec_edit_stats' => [
                ['icon' => '⏳', 'label' => 'معلّق', 'value' => (string) $pending, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.12)'],
                ['icon' => '✅', 'label' => 'مُعتمد', 'value' => (string) $approved, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.12)'],
                ['icon' => '❌', 'label' => 'مرفوض', 'value' => (string) $rejected, 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.12)'],
                ['icon' => '📋', 'label' => 'إجمالي', 'value' => (string) ($pending + $approved + $rejected), 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.12)'],
            ],
            'rejection_reasons' => config('spec_edit.rejection_reasons', []),
        ];
    }

    private function workshopStatistics(): array
    {
        return [
            'workshop_analytics' => app(WorkshopAnalyticsService::class)->build(),
        ];
    }

    private function cashierPayments(): array
    {
        $cases = CaseRecord::query()
            ->awaitingCashier()
            ->with([
                'patient:id,patient_code,name,phone',
                'quotes:id,case_id,quote_no,total,status',
            ])
            ->orderByDesc('updated_at')
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get();

        $awaitingAmount = $cases->sum(fn (CaseRecord $c) => (float) ($c->quotes->sortByDesc('id')->first()?->total ?? $c->quote_total ?? 0));

        $today = Payment::query()->whereDate('received_at', today());
        $todayCount = (clone $today)->count();
        $todayAmount = (clone $today)->sum('amount');

        return [
            'cashier_cases' => $cases,
            'payment_methods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()])
                ->values()
                ->all(),
            'cashier_stats' => [
                ['icon' => '💵', 'label' => 'بانتظار الدفع', 'value' => (string) $cases->count(), 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '⏳', 'label' => 'قيمة بانتظار التحصيل', 'value' => number_format($awaitingAmount, 0).' ج.م', 'color' => '#b45309', 'bg' => 'rgba(217,119,6,0.08)'],
                ['icon' => '✅', 'label' => 'دفعات اليوم', 'value' => (string) $todayCount, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '💰', 'label' => 'محصّل اليوم', 'value' => number_format($todayAmount, 0).' ج.م', 'color' => '#047857', 'bg' => 'rgba(5,150,105,0.08)'],
            ],
        ];
    }

    private function cashierStatistics(): array
    {
        $monthStart = now()->startOfMonth();

        $payments = Payment::query()
            ->with('caseRecord:id,case_no')
            ->orderByDesc('received_at')
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get();

        $monthPayments = $payments->filter(fn (Payment $p) => $p->received_at && $p->received_at->gte($monthStart));

        $byMethod = collect(PaymentMethod::cases())->map(function (PaymentMethod $m) use ($monthPayments) {
            $group = $monthPayments->where('method', $m->value);

            return [
                'method' => $m->label(),
                'count' => $group->count(),
                'total' => (float) $group->sum('amount'),
            ];
        })->values()->all();

        $todayAmount = (float) $payments->filter(fn (Payment $p) => $p->received_at && $p->received_at->isToday())->sum('amount');
        $monthAmount = (float) $monthPayments->sum('amount');

        return [
            'cashier_payments' => $payments,
            'cashier_by_method' => $byMethod,
            'cashier_stats_totals' => [
                ['icon' => '📅', 'label' => 'محصّل اليوم', 'value' => number_format($todayAmount, 0).' ج.م', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '🗓️', 'label' => 'محصّل هذا الشهر', 'value' => number_format($monthAmount, 0).' ج.م', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '🧾', 'label' => 'عدد الدفعات (الشهر)', 'value' => (string) $monthPayments->count(), 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
                ['icon' => '💰', 'label' => 'إجمالي الدفعات', 'value' => (string) $payments->count(), 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
            ],
        ];
    }

    private function workshopDesk(): array
    {
        app(BomService::class)->repairOrphanWipCases();

        $cases = CaseRecord::query()
            ->workshopDeskQueue()
            ->with([
                'patient:id,patient_code,name',
                'bom:id,case_id,bom_no,stage',
                'bom.items:id,bom_id,stock_item_code,name,qty',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $wipCount = $cases->count();
        $milCount = $cases->filter(fn ($c) => $c->isMilitary())->count();
        $civCount = $cases->count() - $milCount;

        return [
            'workshop_cases' => $cases,
            'workshop_stats' => [
                ['icon' => '🏭', 'label' => 'تحت التشغيل', 'value' => (string) $wipCount, 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '📦', 'label' => 'إجمالي الأوامر', 'value' => (string) $cases->count(), 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
            ],
            'workshop_summary' => [
                'wip' => $wipCount,
                'military' => $milCount,
                'civilian' => $civCount,
                'total_active' => $cases->count(),
            ],
        ];
    }

    private function operationsDeliveryDesk(): array
    {
        $cases = CaseRecord::query()
            ->operationsDeliveryQueue()
            ->with([
                'patient:id,patient_code,name',
                'bom:id,case_id,bom_no,stage',
                'bom.items:id,bom_id,stock_item_code,name,qty',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $readyCount = $cases->count();
        $doneCount = CaseRecord::countDeliveredByOps();
        $milCount = $cases->filter(fn ($c) => $c->isMilitary())->count();
        $civCount = $cases->count() - $milCount;

        return [
            'ops_cases' => $cases,
            'ops_stats' => [
                ['icon' => '✅', 'label' => 'جاهز للتسليم', 'value' => (string) $readyCount, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '🪖', 'label' => 'مسار عسكري', 'value' => (string) $milCount, 'color' => '#4f46e5', 'bg' => 'rgba(79,70,229,0.1)'],
                ['icon' => '🌐', 'label' => 'مسار مدني', 'value' => (string) $civCount, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '📁', 'label' => 'تم التسليم', 'value' => (string) $doneCount, 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
            ],
            'ops_summary' => [
                'ready' => $readyCount,
                'done' => $doneCount,
                'military' => $milCount,
                'civilian' => $civCount,
                'total_active' => $readyCount,
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

        $okCount = $items->filter(fn (StockItem $i) => ! $i->isBackorder() && $i->status === StockItem::STATUS_OK)->count();
        $lowCount = $items->filter(fn (StockItem $i) => ! $i->isBackorder() && $i->status === StockItem::STATUS_LOW)->count();
        $backorderCount = $items->filter(fn (StockItem $i) => $i->isBackorder())->count();
        $totalCount = $items->count();

        return [
            'inventory_items' => $items->map(fn (StockItem $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'spec' => $item->spec ?? '',
                'category' => $item->category?->name ?? '',
                'category_id' => $item->category_id,
                'qty' => (int) $item->qty,
                'reserved' => (int) $item->reserved,
                'min_qty' => (int) ($item->min_qty ?? 0),
                'available' => $item->availableQty(),
                'backorder' => $item->backorderQty(),
                'status' => $item->isBackorder() ? 'backorder' : $item->status,
                'barcode' => $item->barcode,
                'last_moved_at' => $item->last_moved_at?->format('d/m/Y'),
            ])->values()->all(),
            'inventory_suppliers' => Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'inventory_stats' => [
                ['icon' => '📦', 'label' => 'إجمالي الأصناف', 'value' => (string) $totalCount, 'color' => '#4338ca', 'bg' => 'rgba(67,56,202,0.1)'],
                ['icon' => '✅', 'label' => 'متوفر', 'value' => (string) $okCount, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '🛒', 'label' => 'طلبات توريد', 'value' => (string) $backorderCount, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.12)'],
                ['icon' => '⚠️', 'label' => 'كمية منخفضة', 'value' => (string) $lowCount, 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
            ],
        ];
    }

    /** @deprecated kept for other dashboards if referenced */
    private function inventoryHealthLabel($items): string
    {
        if ($items->isEmpty()) {
            return '0/100';
        }

        $total = $items->count();
        $okPct = (int) round($items->where('status', StockItem::STATUS_OK)->count() / $total * 100);
        $sufficient = $items->filter(fn (StockItem $i) => $i->qty > StockItem::LOW_QTY_THRESHOLD)->count();
        $suffPct = (int) round($sufficient / $total * 100);
        $coverPct = min(100, (int) round(($total - $items->where('status', StockItem::STATUS_LOW)->count()) / $total * 100 + 15));
        $score = (int) round($okPct * 0.4 + $suffPct * 0.35 + $coverPct * 0.25);

        return $score.'/100';
    }

    private function technicalBom(): array
    {
        $boms = Bom::query()
            ->with([
                'caseRecord:id,case_no,work_order_no,patient_type,manufacturing_stage',
                'items:id,bom_id,stock_item_code,name,qty,issued_qty',
            ])
            ->whereHas('caseRecord', fn ($q) => $q->where('stage_key', CaseRecord::STAGE_MANUFACTURING))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $rawCount = $boms->where('stage', Bom::STAGE_RAW)->count();
        $wipCount = $boms->where('stage', Bom::STAGE_WIP)->count();
        $finCount = $boms->where('stage', Bom::STAGE_FINISHED)->count();

        return [
            'warehouse_boms' => $boms,
            'bom_stats' => [
                ['icon' => StockWarehouseType::Raw->icon(), 'label' => StockWarehouseType::Raw->label(), 'value' => (string) $rawCount, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => StockWarehouseType::Production->icon(), 'label' => 'قيد التصنيع', 'value' => (string) $wipCount, 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => StockWarehouseType::Delivery->icon(), 'label' => StockWarehouseType::Delivery->label(), 'value' => (string) $finCount, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '📋', 'label' => 'إجمالي القوائم', 'value' => (string) $boms->count(), 'bg' => 'rgba(124,58,237,0.1)'],
            ],
        ];
    }

    private function adminCivilianDebts(): array
    {
        $service = app(AdminCivilianDebtService::class);
        $debts = $service->query()->get();

        return [
            'civilian_debts' => $debts,
            'civilian_debts_stats' => $service->stats($debts),
            'civilian_debt_companies' => ContractCompany::query()
                ->where('is_military', false)
                ->orderBy('name')
                ->get(['id', 'name', 'company_code']),
        ];
    }

    private function adminMilitaryDebts(): array
    {
        $debts = MilitaryDebt::query()
            ->with('collectionEntries')
            ->latestFirst()
            ->get();
        $service = app(MilitaryDebtService::class);

        return [
            'military_debts' => $debts,
            'military_debts_stats' => $service->stats($debts),
        ];
    }

    private function adminReturns(): array
    {
        $notes = ReturnNote::query()
            ->with([
                'lines',
                'bom:id,bom_no',
                'createdByUser:id,name',
                'caseRecord:id,case_no,path',
            ])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $barcodes = StockItem::query()
            ->whereIn('code', $notes->flatMap(fn ($n) => $n->lines->pluck('stock_item_code'))->unique()->all())
            ->pluck('barcode', 'code');

        $statusLabels = [
            ReturnNote::STATUS_COMPLETED => 'تم الاستلام',
            ReturnNote::STATUS_PARTIAL => 'استلام جزئي',
            ReturnNote::STATUS_AUTHORIZED => 'بانتظار استلام المخزن',
        ];

        $linesExport = [];
        foreach ($notes as $note) {
            foreach ($note->lines as $line) {
                $linesExport[] = [
                    'return_no' => $note->return_no,
                    'status' => $statusLabels[$note->status] ?? $note->status,
                    'bom_no' => $note->bom?->bom_no ?? '—',
                    'work_order_no' => $note->work_order_no ?? '—',
                    'patient_name' => $note->patient_name,
                    'order_ref' => $note->order_ref ?? '—',
                    'case_no' => $note->caseRecord?->case_no ?? '—',
                    'stock_item_code' => $line->stock_item_code,
                    'item_name' => $line->name ?: $line->stock_item_code,
                    'barcode' => $barcodes[$line->stock_item_code] ?? '—',
                    'qty_requested' => $line->qty_requested,
                    'qty_returned' => $line->qty_returned,
                    'qty_pending' => max(0, $line->qty_requested - $line->qty_returned),
                    'reason' => $line->reason ?? '—',
                    'sent_by' => $note->createdByUser?->name ?? $note->created_by ?? '—',
                    'sent_at' => $note->authorized_at?->format('d/m/Y H:i') ?? '—',
                    'received_at' => $note->completed_at?->format('d/m/Y H:i') ?? '—',
                ];
            }
        }

        $returnMovements = StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->get(['quantity', 'unit_cost']);

        $totalReturnedQty = (int) ReturnNoteLine::query()->sum('qty_returned');
        $totalReturnedValue = $returnMovements->sum(fn ($m) => (float) $m->quantity * (float) $m->unit_cost);

        $authorized = $notes->where('status', ReturnNote::STATUS_AUTHORIZED)->count();
        $partial = $notes->where('status', ReturnNote::STATUS_PARTIAL)->count();
        $completed = $notes->where('status', ReturnNote::STATUS_COMPLETED)->count();

        return [
            'return_notes' => $notes,
            'return_lines_export' => $linesExport,
            'return_barcodes' => $barcodes,
            'return_notes_stats' => [
                ['icon' => '📋', 'label' => 'إجمالي الطلبات', 'value' => (string) $notes->count(), 'bg' => 'rgba(79,70,229,0.1)', 'color' => '#4f46e5'],
                ['icon' => '📤', 'label' => 'بانتظار المخزن', 'value' => (string) $authorized, 'bg' => 'rgba(217,119,6,0.1)', 'color' => '#d97706'],
                ['icon' => '🔄', 'label' => 'استلام جزئي', 'value' => (string) $partial, 'bg' => 'rgba(14,116,144,0.1)', 'color' => '#0e7490'],
                ['icon' => '✅', 'label' => 'تم الاستلام', 'value' => (string) $completed, 'bg' => 'rgba(5,150,105,0.1)', 'color' => '#059669'],
                ['icon' => '📦', 'label' => 'وحدات مرتجعة', 'value' => (string) $totalReturnedQty, 'bg' => 'rgba(124,58,237,0.1)', 'color' => '#7c3aed'],
                ['icon' => '💰', 'label' => 'قيمة المستعاد — متوسط التكلفة المرجح', 'value' => number_format($totalReturnedValue, 0).' ج.م', 'bg' => 'rgba(5,150,105,0.08)', 'color' => '#059669'],
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
            'contracts' => $contracts,
            'is_admin' => $isAdmin,
            'contracts_stats' => [
                ['icon' => '📑', 'label' => $isAdmin ? 'إجمالي الموافقات' : 'إجمالي العقود', 'value' => (string) $contracts->count(), 'bg' => 'rgba(124,58,237,0.1)'],
                ['icon' => '💰', 'label' => 'إجمالي المبالغ', 'value' => number_format($totalAmount, 0), 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '📅', 'label' => 'هذا الشهر', 'value' => (string) $contracts->filter(fn ($c) => $c->approval_date?->isCurrentMonth())->count(), 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '📎', 'label' => 'لها مستندات', 'value' => (string) $contracts->filter(fn ($c) => $c->letter_path)->count(), 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
            ],
        ];
    }

    private function adjustmentsHistory(): array
    {
        $service = app(AdjustmentsTransferHistoryService::class);
        $dates = $service->parseDateRange(
            request()->query('from'),
            request()->query('to'),
        );
        $search = request()->query('search');
        $rows = $service->list($dates['from'], $dates['to'], $search);

        return [
            'history_rows' => $rows,
            'history_stats' => $service->stats($dates['from'], $dates['to'], $search),
            'date_from' => $dates['from']->toDateString(),
            'date_to' => $dates['to']->toDateString(),
            'search_query' => $search ?? '',
        ];
    }

    /**
     * صفحة الإشعارات — أرشيف كامل مع pagination من السيرفر (بلا polling).
     */
    private function notificationsInbox(): array
    {
        $roleSlug = auth()->user()?->role?->slug ?? '';

        if ($roleSlug !== '') {
            app(NotificationService::class)->markAllReadForRole($roleSlug);
        }

        $base = AppNotification::query()->forRole($roleSlug);

        $filter = request()->query('filter', 'all');
        $query = (clone $base)
            ->with(['caseRecord:id,case_no,order_ref'])
            ->latest();

        if ($filter === 'unread') {
            $query->unread();
        }

        $notifications = $query->paginate((int) config('dashboards.table_per_page', 10))->withQueryString();

        $unread = (clone $base)->unread()->count();
        $today = (clone $base)->whereDate('created_at', today())->count();

        return [
            'notifications' => $notifications,
            'notifications_filter' => $filter,
            'notifications_stats' => [
                ['icon' => '🔔', 'label' => 'إجمالي الإشعارات', 'value' => (string) $base->count(), 'bg' => 'rgba(37,99,235,0.1)', 'color' => '#2563eb'],
                ['icon' => '📬', 'label' => 'غير مقروء', 'value' => (string) $unread, 'bg' => 'rgba(220,38,38,0.1)', 'color' => '#dc2626'],
                // ['icon' => '📅', 'label' => 'اليوم', 'value' => (string) $today, 'bg' => 'rgba(5,150,105,0.1)', 'color' => '#059669'],
                // ['icon' => '📄', 'label' => 'هذه الصفحة', 'value' => (string) $notifications->count(), 'bg' => 'rgba(100,116,139,0.1)'],
            ],
        ];
    }
}
