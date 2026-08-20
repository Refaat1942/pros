<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class UserPageAccessService
{
    public const TIER_DEPARTMENT_ADMIN = 'department_admin';

    public const TIER_DEPARTMENT_STAFF = 'department_staff';

    /** @return list<array{key: string, label: string, icon: string}> */
    public function pagesForRole(Role $role): array
    {
        $dashboard = $role->slug;
        $pages = config("dashboards.{$dashboard}.pages", []);
        $result = [];

        foreach ($pages as $key => $meta) {
            if (! empty($meta['hidden'])) {
                continue;
            }

            $result[] = [
                'key' => $key,
                'label' => $meta['label'] ?? $meta['title'] ?? $key,
                'icon' => $meta['icon'] ?? '📄',
            ];
        }

        return $result;
    }

    /** @return list<string> */
    public function defaultStaffPages(string $roleSlug): array
    {
        $defaults = config('staff_page_defaults.'.$roleSlug, ['notifications']);

        return array_values(array_unique($defaults));
    }

    /** @param  list<string>|null  $allowedPages */
    public function normalizeStaffPages(string $roleSlug, ?array $allowedPages): array
    {
        $available = array_column($this->pagesForRole(Role::query()->where('slug', $roleSlug)->firstOrFail()), 'key');

        if ($allowedPages === null || $allowedPages === []) {
            $allowedPages = $this->defaultStaffPages($roleSlug);
        }

        $filtered = array_values(array_intersect($allowedPages, $available));

        if ($filtered === []) {
            return $this->defaultStaffPages($roleSlug);
        }

        if (! in_array('notifications', $filtered, true) && in_array('notifications', $available, true)) {
            $filtered[] = 'notifications';
        }

        return $filtered;
    }

    public function isStaff(User $user): bool
    {
        return $user->access_tier === self::TIER_DEPARTMENT_STAFF;
    }

    public function canViewPage(User $user, string $dashboard, string $page): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->access_tier !== self::TIER_DEPARTMENT_STAFF) {
            return true;
        }

        if ($user->role?->slug !== $dashboard) {
            return true;
        }

        $allowed = is_array($user->allowed_pages) ? $user->allowed_pages : [];

        return in_array($page, $allowed, true);
    }
}
