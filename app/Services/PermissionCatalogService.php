<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;

/**
 * مزامنة كتالوج الصلاحيات مع قاعدة البيانات وإسناد الافتراضي للأدوار.
 */
class PermissionCatalogService
{
    /**
     * يُحدِّث/ينشئ كل صلاحية من الكتالوج ويحذف السجلات القديمة غير المعرَّفة.
     */
    public function syncToDatabase(): void
    {
        $catalog = Permission::catalog();
        $validSlugs = array_keys($catalog);

        foreach ($catalog as $slug => $meta) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                [
                    'label_ar'  => $meta['label_ar'],
                    'group'     => $meta['dashboard'],
                    'type'      => $meta['type'],
                    'dashboard' => $meta['dashboard'],
                    'page'      => $meta['page'] ?? null,
                ],
            );
        }

        Permission::query()
            ->whereNotIn('slug', $validSlugs)
            ->delete();
    }

    /**
     * يُسنِد لكل دور (عدا الأدمن) صلاحيات عرض لوحته + الإجراءات الافتراضية.
     */
    public function seedRoleDefaults(bool $replaceViews = false): void
    {
        $this->syncToDatabase();

        $defaultActions = config('permissions.default_actions', []);

        Role::query()
            ->where('slug', '!=', Role::SLUG_ADMIN)
            ->each(function (Role $role) use ($defaultActions, $replaceViews) {
                $dashboard = $role->slug;

                $viewIds = Permission::query()
                    ->where('type', Permission::TYPE_VIEW)
                    ->where('dashboard', $dashboard)
                    ->pluck('id');

                if ($replaceViews) {
                    $actionIds = $role->permissions()
                        ->where('type', Permission::TYPE_ACTION)
                        ->pluck('permissions.id');
                    $role->permissions()->sync($actionIds->merge($viewIds)->unique()->values());
                } else {
                    $role->permissions()->syncWithoutDetaching($viewIds);
                }

                $actionSlugs = $defaultActions[$dashboard] ?? [];
                if ($actionSlugs !== []) {
                    $actionIds = Permission::whereIn('slug', $actionSlugs)->pluck('id');
                    $role->permissions()->syncWithoutDetaching($actionIds);
                }
            });

        $this->seedAdminCrossDashboardAccess();
    }

    /**
     * يمنح مسؤول النظام صلاحيات عرض كل اللوحات التشغيلية (عدا لوحة الإدارة — وصولها تلقائي).
     */
    private function seedAdminCrossDashboardAccess(): void
    {
        $admin = Role::where('slug', Role::SLUG_ADMIN)->first();

        if (! $admin) {
            return;
        }

        $viewIds = Permission::query()
            ->where('type', Permission::TYPE_VIEW)
            ->where('dashboard', '!=', Role::SLUG_ADMIN)
            ->pluck('id');

        $admin->permissions()->syncWithoutDetaching($viewIds);
    }

    /**
     * بيانات العرض لصفحة مصفوفة الصلاحيات — بطاقة لكل لوحة.
     *
     * @return array{roles: \Illuminate\Support\Collection, dashboards: array, matrix: \Illuminate\Support\Collection}
     */
    public function matrixPageData(): array
    {
        $roles = Role::query()
            ->with('permissions:id,slug')
            ->orderBy('label_ar')
            ->get(['id', 'slug', 'label_ar']);

        $permissions = Permission::query()
            ->orderBy('dashboard')
            ->orderBy('type')
            ->orderBy('id')
            ->get(['id', 'slug', 'label_ar', 'dashboard', 'type', 'page']);

        $labels = config('permissions.dashboard_labels', []);
        $dashboards = [];

        foreach ($labels as $key => $meta) {
            if ($key === Role::SLUG_ADMIN) {
                continue;
            }

            $dashPerms = $permissions->where('dashboard', $key);
            $dashboards[$key] = [
                'key'     => $key,
                'label'   => $meta['label_ar'] ?? $key,
                'icon'    => $meta['icon'] ?? '📊',
                'views'   => $dashPerms->where('type', Permission::TYPE_VIEW)->values(),
                'actions' => $dashPerms->where('type', Permission::TYPE_ACTION)->values(),
            ];
        }

        return [
            'roles'        => $roles,
            'dashboards'   => $dashboards,
            'matrix'         => $roles->mapWithKeys(fn (Role $r) => [
                $r->id => $r->permissions->pluck('slug')->all(),
            ]),
            'permission_ids' => $permissions->pluck('id', 'slug'),
        ];
    }
}
