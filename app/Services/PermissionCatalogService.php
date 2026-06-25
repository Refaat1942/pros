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
     * يُسنِد لكل دور (تشغيلي + أدمن) جميع صلاحيات اللوحات التشغيلية.
     * النتيجة: كل التوجلز خضراء بعد migrate:fresh --seed؛
     * يستطيع الأدمن تضييق الصلاحيات لاحقاً من صفحة المصفوفة.
     *
     * @param bool $fullSync  true = استبدال كامل، false = إضافة بدون حذف الموجود
     */
    public function seedRoleDefaults(bool $fullSync = false): void
    {
        $this->syncToDatabase();

        // جميع الصلاحيات التشغيلية (كل لوحة ما عدا admin)
        $allIds = Permission::query()
            ->where('dashboard', '!=', Role::SLUG_ADMIN)
            ->pluck('id');

        Role::query()->each(function (Role $role) use ($allIds, $fullSync) {
            if ($fullSync) {
                $role->permissions()->sync($allIds);
            } else {
                $role->permissions()->syncWithoutDetaching($allIds);
            }
        });
    }

    /**
     * يمنح مسؤول النظام كل صلاحيات عرض وإجراءات اللوحات التشغيلية.
     * لوحة الإدارة نفسها وصولها تلقائي عبر Gate::before.
     */
    private function seedAdminFullAccess(bool $fullSync = false): void
    {
        $admin = Role::where('slug', Role::SLUG_ADMIN)->first();

        if (! $admin) {
            return;
        }

        $ids = Permission::query()
            ->where('dashboard', '!=', Role::SLUG_ADMIN)
            ->pluck('id');

        if ($fullSync) {
            $admin->permissions()->sync($ids);
        } else {
            $admin->permissions()->syncWithoutDetaching($ids);
        }
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
