<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     *
     * طبقة صلاحيات تفصيلية أصلية فوق نظام الأدوار المخصص:
     *  - الأدمن (السوبر أدمن) يمتلك كل الصلاحيات عبر Gate::before.
     *  - كل صلاحية في Permission::CATALOG تُسجَّل كـ Gate يفوّض إلى User::hasPermission.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user, string $ability) => $user->isAdmin() ? true : null);

        foreach (array_keys(Permission::catalog()) as $slug) {
            Gate::define($slug, fn (User $user) => $user->hasPermission($slug));
        }
    }
}
