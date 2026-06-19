<?php

namespace App\Services\Dashboard;

use App\Models\ContractCompany;
use App\Models\MilitaryRank;
use App\Models\PricingRequest;
use App\Models\Role;
use App\Models\User;
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
            'admin.pricing'         => $this->adminPricing(),
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

        return [
            'employees'      => $employees,
            'roles'          => $roles,
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
}
