<?php

namespace App\Services;

use App\Models\User;

/**
 * تفويض تجميعات نظرة عامة الإدارة — مصدر واحد للصفحة والتصدير.
 */
class AdminOverviewScopeService
{
    public function hasFullOverview(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function canSeeBundle(User $user, string $bundleId): bool
    {
        if ($this->hasFullOverview($user)) {
            return true;
        }

        $def = config("overview_widgets.bundles.{$bundleId}");

        if (! is_array($def)) {
            return false;
        }

        if (isset($def['inherits'])) {
            return $this->canSeeBundle($user, (string) $def['inherits']);
        }

        return $this->matchesDefinition($user, $def);
    }

    /** @return list<string> */
    public function authorizedCycleKeys(User $user): array
    {
        return $this->keysForKind($user, 'cycle_card', 'cycle_key');
    }

    /** @return list<string> */
    public function authorizedCaseStripKeys(User $user): array
    {
        return $this->keysForKind($user, 'case_strip', 'strip_key');
    }

    /** @return list<string> */
    public function authorizedFinanceSectionKeys(User $user): array
    {
        return $this->keysForKind($user, 'finance_section', 'section_key');
    }

    /** @return list<string> */
    public function authorizedBiBoardKeys(User $user): array
    {
        $keys = $this->keysForKind($user, 'bi_board', 'board_key');

        if ($this->authorizedFinanceSectionKeys($user) !== [] && ! in_array('board4', $keys, true)) {
            $keys[] = 'board4';
        }

        return array_values(array_unique(array_filter($keys)));
    }

    public function canSeeCycleTotalActive(User $user): bool
    {
        if ($this->hasFullOverview($user)) {
            return true;
        }

        if ($this->authorizedCycleKeys($user) !== []) {
            return true;
        }

        if ($this->authorizedCaseStripKeys($user) !== []) {
            return true;
        }

        if ($this->authorizedFinanceSectionKeys($user) !== []) {
            return true;
        }

        return $this->authorizedBiBoardKeys($user) !== [];
    }

    /** @return list<string> */
    private function keysForKind(User $user, string $kind, string $keyField): array
    {
        $keys = [];

        foreach (config('overview_widgets.bundles', []) as $bundleId => $def) {
            if (($def['kind'] ?? '') !== $kind) {
                continue;
            }

            if (! $this->canSeeBundle($user, $bundleId)) {
                continue;
            }

            $keys[] = (string) ($def[$keyField] ?? '');
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /** @param array<string, mixed> $def */
    private function matchesDefinition(User $user, array $def): bool
    {
        if (isset($def['all_of'])) {
            foreach ($def['all_of'] as $sub) {
                if (! is_array($sub) || ! $this->matchesDefinition($user, $sub)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($def['any_of'])) {
            foreach ($def['any_of'] as $sub) {
                if (is_array($sub) && $this->matchesDefinition($user, $sub)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($def['dashboards'] ?? [] as $dashboard) {
            if ($user->canAccessDashboard((string) $dashboard)) {
                return true;
            }
        }

        foreach ($def['admin_pages'] ?? [] as $page) {
            if ($user->canViewDashboardPage('admin', (string) $page)) {
                return true;
            }
        }

        foreach ($def['desk_pages'] ?? [] as $pair) {
            if (! is_array($pair) || count($pair) < 2) {
                continue;
            }

            if ($user->canViewDashboardPage((string) $pair[0], (string) $pair[1])) {
                return true;
            }
        }

        foreach ($def['permissions'] ?? [] as $permission) {
            if ($user->hasPermission((string) $permission)) {
                return true;
            }
        }

        return false;
    }
}
