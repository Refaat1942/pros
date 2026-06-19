<?php

namespace App\Services\Dashboard;

use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\MedicalRecord;
use App\Models\MilitaryRank;
use App\Models\PricingRequest;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\VisitType;
use App\Enums\PricingRequestStatus;

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
            'admin.debts'           => $this->adminDebts(),
            'admin.military-ranks'  => $this->adminMilitaryRanks(),
            'admin.visit-types'     => $this->adminVisitTypes(),
            'admin.pricing'         => $this->adminPricing(),
            'reception.appointments'=> $this->receptionAppointments(),
            'reception.patients'    => $this->receptionPatients(),
            'reception.delivery'    => $this->receptionDelivery(),
            'doctor.queue'          => $this->doctorQueue(),
            'doctor.diagnosis'      => $this->doctorDiagnosis(),
            'spec.orders'           => $this->specOrders(),
            'spec.pricing'          => $this->specPricing(),
            'spec.spec'             => $this->specPreview(),
            'operations.operations' => $this->operationsDesk(),
            'technical.bom'         => $this->technicalBom(),
            default                 => [],
        };
    }

    private function adminEmployees(): array
    {
        $employees = User::query()
            ->with('role:id,slug,label_ar')
            ->orderBy('name')
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
            ->orderBy('name')
            ->get();

        return ['companies' => $companies];
    }

    private function adminDebts(): array
    {
        $companies = ContractCompany::query()
            ->with('debt')
            ->whereHas('debt')
            ->orderBy('name')
            ->get();

        $totalDue       = $companies->sum(fn ($c) => (float) ($c->debt?->due ?? 0));
        $totalCollected = $companies->sum(fn ($c) => (float) ($c->debt?->collected ?? 0));

        return [
            'debt_companies' => $companies,
            'debt_stats'     => [
                ['icon' => '📋', 'label' => 'جهات', 'value' => (string) $companies->count(), 'bg' => 'rgba(124,58,237,0.1)'],
                ['icon' => '💳', 'label' => 'المستحق', 'value' => number_format($totalDue, 0), 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
                ['icon' => '✅', 'label' => 'المحصّل', 'value' => number_format($totalCollected, 0), 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '⏳', 'label' => 'المتبقي', 'value' => number_format(max(0, $totalDue - $totalCollected), 0), 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
            ],
        ];
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
                ->orderBy('name')
                ->get(),
        ];
    }

    private function adminPricing(): array
    {
        return [
            'pricing_requests' => PricingRequest::query()
                ->with(['caseRecord:id,case_no,stage_key'])
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

    private function receptionAppointments(): array
    {
        return [
            'military_ranks' => MilitaryRank::active()
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
            'visit_types' => VisitType::active()
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    private function receptionPatients(): array
    {
        return [
            'patients' => \App\Models\Patient::query()
                ->with('contractCompany:id,name')
                ->orderByDesc('registered_at')
                ->limit(100)
                ->get(),
        ];
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

        return [
            'delivery_cases' => $cases,
            'delivery_stats' => [
                ['icon' => '✅', 'label' => 'جاهز للتسليم', 'value' => (string) $cases->count(), 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '🪖', 'label' => 'عسكري', 'value' => (string) $cases->filter(fn ($c) => $c->isMilitary())->count(), 'color' => '#4f46e5', 'bg' => 'rgba(79,70,229,0.1)'],
                ['icon' => '🌐', 'label' => 'مدني', 'value' => (string) $cases->filter(fn ($c) => ! $c->isMilitary())->count(), 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '📋', 'label' => 'BOM تام', 'value' => (string) $cases->count(), 'bg' => 'rgba(5,150,105,0.1)'],
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

    private function specOrders(): array
    {
        $cases = CaseRecord::query()
            ->with([
                'patient:id,patient_code,name,patient_type,company_name',
                'techOrderSpec:id,case_id,locked,submitted_at',
            ])
            ->where('stage_key', CaseRecord::STAGE_TECHNICAL)
            ->orderByDesc('created_at')
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
            ->with('caseRecord:id,case_no,order_ref,stage_key')
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $awaiting = $requests->filter(fn ($r) => $r->status_key === PricingRequestStatus::AwaitingAdminApproval)->count();
        $sent     = $requests->filter(fn ($r) => $r->status_key === PricingRequestStatus::SentToReception)->count();

        return [
            'spec_pricing_requests' => $requests,
            'spec_pricing_stats'    => [
                ['icon' => '📋', 'label' => 'طلبات', 'value' => (string) $requests->count(), 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '⏳', 'label' => 'انتظار موافقة الأدمن', 'value' => (string) $awaiting, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
                ['icon' => '✅', 'label' => 'جاهز للاستقبال', 'value' => (string) $sent, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '🔩', 'label' => 'متوسط البنود', 'value' => $requests->count() ? (string) round($requests->avg('items_count')) : '0', 'bg' => 'rgba(217,119,6,0.1)'],
            ],
        ];
    }

    private function specPreview(): array
    {
        $specs = \App\Models\TechOrderSpec::query()
            ->where('locked', true)
            ->with(['items', 'caseRecord:id,case_no,order_ref'])
            ->orderByDesc('submitted_at')
            ->limit(50)
            ->get();

        return ['submitted_specs' => $specs];
    }

    private function operationsDesk(): array
    {
        $cases = CaseRecord::query()
            ->with([
                'patient:id,patient_code,name',
                'bom:id,case_id,bom_no,stage',
                'bom.items:id,bom_id',
            ])
            ->where('stage_key', CaseRecord::STAGE_MANUFACTURING)
            ->orderByDesc('updated_at')
            ->get();

        $rawCount  = $cases->filter(fn ($c) => $c->bom?->stage === \App\Models\Bom::STAGE_RAW)->count();
        $wipCount  = $cases->filter(fn ($c) => $c->bom?->stage === \App\Models\Bom::STAGE_WIP)->count();
        $milCount  = $cases->filter(fn ($c) => $c->isMilitary())->count();
        $civCount  = $cases->count() - $milCount;

        return [
            'ops_cases'  => $cases,
            'ops_stats'  => [
                ['icon' => '🎯', 'label' => 'أوامر نشطة', 'value' => (string) $cases->count(), 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
                ['icon' => '🪖', 'label' => 'مسار عسكري', 'value' => (string) $milCount, 'color' => '#4f46e5', 'bg' => 'rgba(79,70,229,0.1)'],
                ['icon' => '🌐', 'label' => 'مسار مدني', 'value' => (string) $civCount, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
                ['icon' => '📦', 'label' => 'بانتظار الصرف', 'value' => (string) $rawCount, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
            ],
            'ops_summary' => [
                'raw'  => $rawCount,
                'wip'  => $wipCount,
                'done' => $cases->filter(fn ($c) => $c->bom?->stage === \App\Models\Bom::STAGE_FINISHED)->count(),
            ],
        ];
    }

    private function technicalBom(): array
    {
        $boms = \App\Models\Bom::query()
            ->with([
                'caseRecord:id,case_no,work_order_no,patient_type,manufacturing_stage',
                'items:id,bom_id,stock_item_code,name,qty,issued_qty',
            ])
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
}
