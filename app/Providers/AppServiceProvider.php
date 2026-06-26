<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Models\Bom;
use App\Models\ContractCompanyDebt;
use App\Models\MilitaryDebt;
use App\Models\ReturnNote;
use App\Services\Dashboard\DashboardQueueService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'bom' => Bom::class,
            'return_note' => ReturnNote::class,
            'contract_company_debt' => ContractCompanyDebt::class,
            'military_debt' => MilitaryDebt::class,
        ]);

        View::composer('partials.dashboard-sidebar', function ($view) {
            $dashboardKey = $view->getData()['dashboardKey'] ?? '';
            $roleSlug     = Auth::user()?->role?->slug;

            $badges = $view->getData()['sidebarBadges'] ?? [];

            if ($roleSlug) {
                $badges['notifications'] = AppNotification::forRole($roleSlug)->unread()->count();
            }

            if ($dashboardKey === 'doctor') {
                $badges['queue'] = app(DashboardQueueService::class)->doctorWaitingCount();
            }

            $view->with('sidebarBadges', $badges);
        });
    }
}
