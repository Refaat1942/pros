<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Models\Bom;
use App\Models\ReturnNote;
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
        ]);

        View::composer('partials.dashboard-sidebar', function ($view) {
            $dashboardKey = $view->getData()['dashboardKey'] ?? '';
            $roleSlug     = Auth::user()?->role?->slug;

            $badges = $view->getData()['sidebarBadges'] ?? [];

            if ($roleSlug) {
                $badges['notifications'] = AppNotification::forRole($roleSlug)->unread()->count();
            }

            $view->with('sidebarBadges', $badges);
        });
    }
}
