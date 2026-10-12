<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

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
                    'label_ar' => $meta['label_ar'],
                    'group' => $meta['dashboard'],
                    'type' => $meta['type'],
                    'dashboard' => $meta['dashboard'],
                    'page' => $meta['page'] ?? null,
                ],
            );
        }

        Permission::query()
            ->whereNotIn('slug', $validSlugs)
            ->delete();
    }

    /**
     * يضيف الصلاحيات الجديدة فقط (صفحة أو إجراء أُضيف بعد آخر مزامنة) ويمنح كل جديد
     * للأدوار التي يتبعها افتراضياً — دون إعادة صلاحية سحبها المدير عمداً من دور.
     * كانت الصفحات الجديدة بلا صف صلاحية حتى تُشغَّل هجرة مزامنة، فلا يراها إلا السوبر أدمن
     * ولا تظهر في المصفوفة (مثال: «طلب التوريد» و«استلام الوارد» لأمين المخزن).
     *
     * @return list<string> slugs الصلاحيات المضافة
     */
    public function syncNewPermissions(): array
    {
        $existing = Permission::query()->pluck('slug')->all();
        $this->syncToDatabase();

        $new = array_values(array_diff(array_keys(Permission::catalog()), $existing));
        if ($new === []) {
            return [];
        }

        $newIds = Permission::query()->whereIn('slug', $new)->pluck('id');

        Role::query()->each(function (Role $role) use ($newIds) {
            if ($role->slug === Role::SLUG_SUPER_ADMIN) {
                return;
            }

            $grant = $this->defaultPermissionIdsForRole($role->slug)->intersect($newIds);
            if ($grant->isNotEmpty()) {
                $role->permissions()->syncWithoutDetaching($grant->all());
            }
        });

        return $new;
    }

    /**
     * يُسنِد لكل دور صلاحيات لوحته فقط + الإجراءات الافتراضية من config/permissions.php.
     *
     * @param  bool  $fullSync  true = استبدال كامل، false = إضافة بدون حذف الموجود
     */
    public function seedRoleDefaults(bool $fullSync = false): void
    {
        $this->syncToDatabase();

        Role::query()->each(function (Role $role) use ($fullSync) {
            if ($role->slug === Role::SLUG_SUPER_ADMIN) {
                return;
            }

            $permissionIds = $this->defaultPermissionIdsForRole($role->slug);

            if ($fullSync) {
                $role->permissions()->sync($permissionIds);
            } else {
                $role->permissions()->syncWithoutDetaching($permissionIds);
            }
        });
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function defaultPermissionIdsForRole(string $roleSlug): \Illuminate\Support\Collection
    {
        $slugs = collect();

        if ($roleSlug === Role::SLUG_ADMIN) {
            foreach (array_keys(config('dashboards.admin.pages', [])) as $page) {
                if ($page === 'notifications') {
                    continue;
                }
                $slugs->push(Permission::viewSlug('admin', $page));
            }
        } elseif (config("dashboards.{$roleSlug}.pages")) {
            foreach (config("dashboards.{$roleSlug}.pages", []) as $pageKey => $pageMeta) {
                if ($pageKey === 'notifications') {
                    continue;
                }
                if (! empty($pageMeta['hidden'])) {
                    continue;
                }
                $slugs->push(Permission::viewSlug($roleSlug, $pageKey));
            }
        }

        foreach (config("permissions.default_actions.{$roleSlug}", []) as $actionSlug) {
            $slugs->push($actionSlug);
        }

        return Permission::query()
            ->whereIn('slug', $slugs->unique()->values()->all())
            ->pluck('id');
    }

    /**
     * بيانات العرض لصفحة مصفوفة الصلاحيات — بطاقة لكل لوحة.
     *
     * @return array{roles: Collection, dashboards: array, matrix: Collection}
     */
    public function matrixPageData(): array
    {
        $roles = Role::query()
            ->where('slug', '!=', Role::SLUG_SUPER_ADMIN)
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
            $dashPerms = $permissions->where('dashboard', $key);
            // الصفحات بنفس ترتيب السايد بار (ترتيب config/dashboards.php) لا بترتيب إنشاء الصلاحية.
            $pageOrder = array_flip(array_keys(config("dashboards.{$key}.pages", [])));
            $views = $dashPerms->where('type', Permission::TYPE_VIEW)
                ->sortBy(fn (Permission $p) => [$pageOrder[$p->page] ?? PHP_INT_MAX, $p->id])
                ->values();
            $entry = [
                'key' => $key,
                'label' => $meta['label_ar'] ?? $key,
                'icon' => $meta['icon'] ?? '📊',
                'views' => $views,
                'actions' => $dashPerms->where('type', Permission::TYPE_ACTION)->values(),
                'groups' => [],
            ];

            $navGroups = config("dashboards.{$key}.nav_groups", []);
            if ($navGroups !== []) {
                $grouped = [];
                foreach ($navGroups as $group) {
                    $pageKeys = array_values($group['pages'] ?? []);
                    $groupViews = $views
                        ->filter(fn (Permission $p) => in_array($p->page, $pageKeys, true))
                        ->sortBy(fn (Permission $p) => array_search($p->page, $pageKeys, true))
                        ->values();
                    if ($groupViews->isNotEmpty()) {
                        $entry['groups'][] = [
                            'label' => $group['label'] ?? '',
                            'icon' => $group['icon'] ?? '📁',
                            'views' => $groupViews,
                        ];
                        array_push($grouped, ...$groupViews->pluck('id')->all());
                    }
                }

                // صفحات ليست في أي مجموعة بالسايد بار (فرعية/مخفية) — تظهر في الآخر بدل أن تختفي من المصفوفة.
                $rest = $views->reject(fn (Permission $p) => in_array($p->id, $grouped, true))->values();
                if ($rest->isNotEmpty()) {
                    $entry['groups'][] = [
                        'label' => 'صفحات فرعية (خارج القائمة الجانبية)',
                        'icon' => '🗂️',
                        'views' => $rest,
                    ];
                }
            }

            $dashboards[$key] = $entry;
        }

        return [
            'roles' => $roles,
            'dashboards' => $dashboards,
            'matrix' => $roles->mapWithKeys(fn (Role $r) => [
                $r->id => $r->permissions->pluck('slug')->all(),
            ]),
            'permission_ids' => $permissions->pluck('id', 'slug'),
        ];
    }
}
