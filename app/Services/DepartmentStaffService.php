<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * إدارة موظفي القسم — مدير القسم يضيف/يعدّل موظفين تحت إشرافه فقط.
 */
class DepartmentStaffService
{
    public function isDepartmentManager(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        if (in_array($user->role?->slug, [Role::SLUG_ADMIN, Role::SLUG_SUPER_ADMIN], true)) {
            return false;
        }

        return $user->access_tier !== UserPageAccessService::TIER_DEPARTMENT_STAFF;
    }

    public function canAccessStaffPage(User $user, string $dashboardKey): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->canViewDashboardPage('admin', 'employees')) {
            return true;
        }

        return $this->isDepartmentManager($user) && $user->role?->slug === $dashboardKey;
    }

    /** @return Builder<User> */
    public function queryForManager(User $manager): Builder
    {
        $manager->loadMissing('role:id,slug');

        return User::query()
            ->with('role:id,slug,label_ar')
            ->where('role_id', $manager->role_id)
            ->where('access_tier', UserPageAccessService::TIER_DEPARTMENT_STAFF)
            ->orderByDesc('id');
    }

    public function canManage(User $manager, User $target): bool
    {
        $manager->loadMissing('role:id,slug');
        $target->loadMissing('role:id,slug');

        if ($manager->id === $target->id) {
            return false;
        }

        if (in_array($target->role?->slug, [Role::SLUG_ADMIN, Role::SLUG_SUPER_ADMIN], true)) {
            return false;
        }

        if ($target->access_tier !== UserPageAccessService::TIER_DEPARTMENT_STAFF) {
            return false;
        }

        if ($manager->isSuperAdmin() || $manager->canViewDashboardPage('admin', 'employees')) {
            return $manager->role_id === $target->role_id;
        }

        if (! $this->isDepartmentManager($manager)) {
            return false;
        }

        return $manager->role_id === $target->role_id;
    }

    public function assertCanManage(User $target): void
    {
        /** @var User|null $manager */
        $manager = Auth::user();

        if ($manager === null || ! $this->canManage($manager, $target)) {
            abort(403, 'غير مصرّح — لا يمكنك إدارة هذا الموظف.');
        }
    }

    public function staffRedirectRoute(User $manager): string
    {
        $slug = $manager->role?->slug ?? 'home';

        return "{$slug}.staff";
    }
}
