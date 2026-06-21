<?php

namespace App\Providers;

use App\Models\Bom;
use App\Models\ReturnNote;
use App\Services\Dashboard\DashboardQueueService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
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
        ]);

        View::composer('partials.dashboard-sidebar', function ($view) {
            $dashboardKey = $view->getData()['dashboardKey'] ?? '';

            if ($dashboardKey !== 'admin') {
                return;
            }

            $view->with('sidebarBadges', [
                'pricing' => app(DashboardQueueService::class)->adminPricingAwaitingCount(),
            ]);
        });
    }
}
