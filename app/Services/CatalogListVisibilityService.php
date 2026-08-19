<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\CatalogColumns;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * إظهار قوائم الأصناف وأعمدتها — افتراضيًا حسب الدور، قابل للتخصيص من الإدارة.
 */
class CatalogListVisibilityService
{
    public const SETTING_KEY = 'catalog_list_visibility';

    /** @return array<string, array{label_ar: string, dashboard: string, page: string}> */
    public function profiles(): array
    {
        return config('catalog_lists.profiles', []);
    }

    /** @return array<string, array{label: string, gate?: string}> */
    public function columnDefinitions(string $profile): array
    {
        if ($profile === 'admin_catalog') {
            $defs = CatalogColumns::definitions();
            $gates = config('catalog_lists.admin_catalog_gates', []);
            $out = [];
            foreach ($defs as $key => $def) {
                if (! ($def['table'] ?? false)) {
                    continue;
                }
                $entry = ['label' => $def['label'] ?? $key];
                if (isset($gates[$key])) {
                    $entry['gate'] = $gates[$key];
                }
                $out[$key] = $entry;
            }

            return $out;
        }

        return config("catalog_lists.profile_columns.{$profile}", []);
    }

    public function isListEnabledForUser(User $user, string $profile): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $roleSlug = $user->role?->slug ?? '';
        if ($roleSlug === '') {
            return false;
        }

        $roleConfig = $this->roleProfileConfig($roleSlug, $profile);

        return (bool) ($roleConfig['enabled'] ?? false);
    }

    /** @return list<string> */
    public function visibleColumnsForUser(User $user, string $profile): array
    {
        if ($user->isSuperAdmin()) {
            return $this->allColumnKeys($profile);
        }

        if (! $this->isListEnabledForUser($user, $profile)) {
            return [];
        }

        $roleSlug = $user->role?->slug ?? '';
        $roleConfig = $this->roleProfileConfig($roleSlug, $profile);
        $columns = is_array($roleConfig['columns'] ?? null) ? $roleConfig['columns'] : [];

        $valid = array_flip($this->allColumnKeys($profile));
        $columns = array_values(array_filter(
            $columns,
            fn (string $key) => isset($valid[$key]),
        ));

        $columns = $this->ensureRequiredColumns($profile, $columns);

        return array_values(array_filter(
            $columns,
            fn (string $key) => $this->columnAllowedByGate($user, $profile, $key),
        ));
    }

    /** @return list<string> */
    public function tableOrderForUser(?User $user, string $profile = 'admin_catalog'): array
    {
        if ($profile !== 'admin_catalog') {
            return $user ? $this->visibleColumnsForUser($user, $profile) : $this->allColumnKeys($profile);
        }

        if ($user === null) {
            return CatalogColumns::tableOrder();
        }

        $visible = $this->visibleColumnsForUser($user, $profile);
        if ($visible === []) {
            return [];
        }

        $order = CatalogColumns::tableOrder();

        return array_values(array_filter(
            $order,
            fn (string $key) => in_array($key, $visible, true),
        ));
    }

    public function tableColspanForUser(?User $user, string $profile = 'admin_catalog', bool $withBarcodeCheckbox = false): int
    {
        $count = count($this->tableOrderForUser($user, $profile));

        return $count + 1 + ($withBarcodeCheckbox ? 1 : 0);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function filterItemFields(array $item, User $user, string $profile): array
    {
        $visible = $this->visibleColumnsForUser($user, $profile);
        if ($visible === []) {
            return [];
        }

        $map = $this->itemFieldMap($profile);
        $allowedFields = [];
        foreach ($visible as $column) {
            foreach ($map[$column] ?? [$column] as $field) {
                $allowedFields[$field] = true;
            }
        }

        $filtered = [];
        foreach ($item as $key => $value) {
            if (isset($allowedFields[$key])) {
                $filtered[$key] = $value;
            }
        }

        if (isset($item['id'])) {
            $filtered['id'] = $item['id'];
        }

        return $filtered;
    }

    /** @return array<string, array<string, bool>> */
    public function all(): array
    {
        return Cache::rememberForever('settings.catalog_list_visibility', function () {
            $raw = Setting::query()->where('key', self::SETTING_KEY)->value('value');
            $stored = $raw ? json_decode($raw, true) : [];
            if (! is_array($stored)) {
                $stored = [];
            }

            $roles = [];
            foreach (Role::query()->orderBy('id')->pluck('slug') as $slug) {
                $roles[$slug] = $this->mergeRoleDefaults($slug, $stored['roles'][$slug] ?? []);
            }

            return ['roles' => $roles];
        });
    }

    /**
     * @return list<array{slug: string, label_ar: string, profiles: list<array<string, mixed>>}>
     */
    public function catalogForAdmin(): array
    {
        $stored = $this->all();
        $out = [];

        foreach (Role::query()->orderBy('id')->get(['slug', 'label_ar']) as $role) {
            $profiles = [];
            foreach ($this->profiles() as $profileKey => $profileMeta) {
                $roleConfig = $stored['roles'][$role->slug][$profileKey]
                    ?? $this->defaultRoleProfile($role->slug, $profileKey);
                $columns = [];
                foreach ($this->columnDefinitions($profileKey) as $colKey => $colMeta) {
                    $columns[] = [
                        'key' => $colKey,
                        'label' => $colMeta['label'] ?? $colKey,
                        'gate' => $colMeta['gate'] ?? null,
                        'visible' => in_array($colKey, $roleConfig['columns'] ?? [], true),
                    ];
                }
                $profiles[] = [
                    'key' => $profileKey,
                    'label_ar' => $profileMeta['label_ar'] ?? $profileKey,
                    'dashboard' => $profileMeta['dashboard'] ?? '',
                    'page' => $profileMeta['page'] ?? '',
                    'enabled' => (bool) ($roleConfig['enabled'] ?? false),
                    'columns' => $columns,
                ];
            }
            $out[] = [
                'slug' => $role->slug,
                'label_ar' => $role->label_ar,
                'profiles' => $profiles,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, array{enabled?: bool, columns?: list<string>}>>  $input
     */
    public function update(array $input): void
    {
        $normalized = ['roles' => []];

        foreach (Role::query()->pluck('slug') as $slug) {
            $roleInput = $input['roles'][$slug] ?? [];
            foreach ($this->profiles() as $profileKey => $profileMeta) {
                $profileInput = $roleInput[$profileKey] ?? [];
                $allKeys = $this->allColumnKeys($profileKey);
                $selected = array_values(array_filter(
                    is_array($profileInput['columns'] ?? null) ? $profileInput['columns'] : [],
                    fn ($key) => is_string($key) && in_array($key, $allKeys, true),
                ));
                $enabled = filter_var($profileInput['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($enabled) {
                    $selected = $this->ensureRequiredColumns($profileKey, $selected);
                }
                $normalized['roles'][$slug][$profileKey] = [
                    'enabled' => $enabled,
                    'columns' => $selected,
                ];
            }
        }

        Setting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($normalized, JSON_UNESCAPED_UNICODE)],
        );

        Cache::forget('settings.catalog_list_visibility');
    }

    /** @return list<string> */
    private function allColumnKeys(string $profile): array
    {
        return array_keys($this->columnDefinitions($profile));
    }

    /** @return array{enabled: bool, columns: list<string>} */
    private function defaultRoleProfile(string $roleSlug, string $profile): array
    {
        $profileMeta = $this->profiles()[$profile] ?? [];
        $defaultRoles = $profileMeta['default_roles'] ?? [];
        $enabled = in_array($roleSlug, $defaultRoles, true);
        $columns = $profileMeta['default_columns'] ?? $this->allColumnKeys($profile);

        return [
            'enabled' => $enabled,
            'columns' => $this->ensureRequiredColumns($profile, $columns),
        ];
    }

    /** @return array{enabled: bool, columns: list<string>} */
    private function roleProfileConfig(string $roleSlug, string $profile): array
    {
        $stored = $this->all();
        $raw = $stored['roles'][$roleSlug][$profile] ?? null;

        if (! is_array($raw)) {
            return $this->defaultRoleProfile($roleSlug, $profile);
        }

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'columns' => $this->ensureRequiredColumns(
                $profile,
                is_array($raw['columns'] ?? null) ? $raw['columns'] : [],
            ),
        ];
    }

    /**
     * @param  array<string, array<string, array{enabled?: bool, columns?: list<string>}>>  $storedRole
     * @return array<string, array{enabled: bool, columns: list<string>}>
     */
    private function mergeRoleDefaults(string $roleSlug, array $storedRole): array
    {
        $merged = [];
        foreach ($this->profiles() as $profileKey => $profileMeta) {
            if (isset($storedRole[$profileKey]) && is_array($storedRole[$profileKey])) {
                $merged[$profileKey] = [
                    'enabled' => (bool) ($storedRole[$profileKey]['enabled'] ?? false),
                    'columns' => $this->ensureRequiredColumns(
                        $profileKey,
                        is_array($storedRole[$profileKey]['columns'] ?? null)
                            ? $storedRole[$profileKey]['columns']
                            : [],
                    ),
                ];
            } else {
                $merged[$profileKey] = $this->defaultRoleProfile($roleSlug, $profileKey);
            }
        }

        return $merged;
    }

    /** @param  list<string>  $columns */
    private function ensureRequiredColumns(string $profile, array $columns): array
    {
        $required = config("catalog_lists.required_columns.{$profile}", ['code', 'name']);
        foreach ($required as $key) {
            if (! in_array($key, $columns, true)) {
                array_unshift($columns, $key);
            }
        }

        return array_values(array_unique($columns));
    }

    private function columnAllowedByGate(User $user, string $profile, string $column): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $defs = $this->columnDefinitions($profile);
        $gate = $defs[$column]['gate'] ?? null;
        if ($gate === null || $gate === '') {
            return true;
        }

        return Gate::forUser($user)->allows($gate);
    }

    /** @return array<string, list<string>> */
    private function itemFieldMap(string $profile): array
    {
        if ($profile === 'admin_catalog') {
            $map = [];
            foreach (CatalogColumns::definitions() as $key => $def) {
                $field = $def['field'] ?? $key;
                $map[$key] = [$field];
                if ($key === 'code') {
                    $map[$key] = ['code', 'catalog_number'];
                }
                if ($key === 'catalog_balance') {
                    $map[$key] = ['catalog_balance', 'balance'];
                }
                if ($key === 'warehouse_qty') {
                    $map[$key] = ['warehouse_qty', 'qty'];
                }
            }

            return $map;
        }

        return config("catalog_lists.item_field_map.{$profile}", []);
    }
}
