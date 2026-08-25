<?php

namespace App\Services;

use App\Models\User;

/**
 * تفويض أقسام مركز التقارير — مصدر واحد للفهرس، العرض، والتصدير.
 */
class AdminReportsScopeService
{
    public function hasFullReports(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function canSeeSection(User $user, string $sectionId): bool
    {
        if ($this->hasFullReports($user)) {
            return true;
        }

        $def = config("reports_sections.sections.{$sectionId}");

        if (! is_array($def)) {
            return false;
        }

        if (isset($def['inherits'])) {
            return $this->canSeeSection($user, (string) $def['inherits']);
        }

        return $this->matchesDefinition($user, $def);
    }

    /** @return list<string> */
    public function authorizedSectionIds(User $user): array
    {
        if ($this->hasFullReports($user)) {
            return array_keys(config('reports_sections.sections', []));
        }

        $ids = [];

        foreach (config('reports_sections.sections', []) as $sectionId => $def) {
            if ($this->canSeeSection($user, (string) $sectionId)) {
                $ids[] = (string) $sectionId;
            }
        }

        return $ids;
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
