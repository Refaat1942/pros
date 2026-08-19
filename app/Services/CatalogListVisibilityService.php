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

    /** @return array<string, array{label_ar: string, default_roles: list<string>, profiles: list<string>}> */
    public function sections(): array
    {
        return config('catalog_lists.sections', []);
    }

    /** @return array<string, array{label_ar: string, dashboard: string, page: string, section?: string}> */
    public function profiles(): array
    {
        return config('catalog_lists.profiles', []);
    }

    public function sectionForProfile(string $profile): ?string
    {
        return $this->profiles()[$profile]['section'] ?? null;
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

    public function isSectionEnabledForUser(User $user, string $section): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $roleSlug = $user->role?->slug ?? '';
        if ($roleSlug === '') {
            return false;
        }

        $roleConfig = $this->roleSectionConfig($roleSlug, $section, $user);

        return (bool) ($roleConfig['enabled'] ?? false);
    }

    public function isListEnabledForUser(User $user, string $profile): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $section = $this->sectionForProfile($profile);
        if ($section !== null && ! $this->isSectionEnabledForUser($user, $section)) {
            return false;
        }

        $roleSlug = $user->role?->slug ?? '';
        if ($roleSlug === '') {
            return false;
        }

        $roleConfig = $this->roleProfileConfig($roleSlug, $profile, $user);

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
        $roleConfig = $this->roleProfileConfig($roleSlug, $profile, $user);
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

    /**
     * @param  list<string>  $alwaysInclude
     * @return list<string>
     */
    public function itemDbColumnsForUser(User $user, string $profile, array $alwaysInclude = ['id']): array
    {
        if (! $this->isListEnabledForUser($user, $profile)) {
            return [];
        }

        $map = $this->itemFieldMap($profile);
        $visible = $this->visibleColumnsForUser($user, $profile);
        $columns = $alwaysInclude;

        foreach ($visible as $column) {
            foreach ($map[$column] ?? [$column] as $field) {
                $columns[] = $field;
            }
        }

        return array_values(array_unique($columns));
    }

    /** @return array{sections: array<string, array{roles: array<string, array{enabled: bool}>>>, roles: array<string, array<string, array{enabled: bool, columns: list<string>}>>} */
    public function all(): array
    {
        return Cache::rememberForever('settings.catalog_list_visibility', function () {
            $raw = Setting::query()->where('key', self::SETTING_KEY)->value('value');
            $stored = $raw ? json_decode($raw, true) : [];
            if (! is_array($stored)) {
                $stored = [];
            }

            $sections = [];
            foreach ($this->sections() as $sectionKey => $sectionMeta) {
                $sectionRoles = [];
                foreach (Role::query()->orderBy('id')->pluck('slug') as $slug) {
                    $sectionRoles[$slug] = $this->mergeSectionRoleDefault(
                        $slug,
                        $sectionKey,
                        $stored['sections'][$sectionKey]['roles'][$slug] ?? [],
                    );
                }
                $sections[$sectionKey] = ['roles' => $sectionRoles];
            }

            $roles = [];
            foreach (Role::query()->orderBy('id')->pluck('slug') as $slug) {
                $roles[$slug] = $this->mergeRoleDefaults($slug, $stored['roles'][$slug] ?? []);
            }

            return [
                'sections' => $sections,
                'roles' => $roles,
            ];
        });
    }

    /** @return list<string> */
    public function profilesForRole(string $roleSlug): array
    {
        $dashboard = $roleSlug === Role::SLUG_SUPER_ADMIN ? Role::SLUG_ADMIN : $roleSlug;
        $out = [];

        foreach ($this->profiles() as $key => $meta) {
            if (($meta['dashboard'] ?? '') === $dashboard) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /**
     * @param  array{sections?: array<string, array{enabled?: bool}>, profiles?: array<string, array{enabled?: bool, columns?: list<string>}>}|null  $userStored
     * @return array{role_slug: string, sections: list<array<string, mixed>>, profiles: list<array<string, mixed>>, has_profiles: bool}
     */
    public function catalogForRole(string $roleSlug, ?array $userStored = null): array
    {
        $stored = $this->all();
        $profileKeys = $this->profilesForRole($roleSlug);
        $sectionGroups = [];
        $standaloneProfiles = [];

        foreach ($this->profiles() as $profileKey => $profileMeta) {
            if (! in_array($profileKey, $profileKeys, true)) {
                continue;
            }

            $roleConfig = $this->resolveProfileConfig($roleSlug, $profileKey, $userStored, $stored);
            $profilePayload = $this->buildProfilePayload($profileKey, $profileMeta, $roleConfig);

            $section = $profileMeta['section'] ?? null;
            if ($section !== null && isset($this->sections()[$section])) {
                if (! isset($sectionGroups[$section])) {
                    $sectionRole = $this->resolveSectionConfig($roleSlug, $section, $userStored, $stored);
                    $sectionGroups[$section] = [
                        'key' => $section,
                        'label_ar' => $this->sections()[$section]['label_ar'] ?? $section,
                        'enabled' => (bool) ($sectionRole['enabled'] ?? false),
                        'profiles' => [],
                    ];
                }
                $sectionGroups[$section]['profiles'][] = $profilePayload;
            } else {
                $standaloneProfiles[] = $profilePayload;
            }
        }

        return [
            'role_slug' => $roleSlug,
            'sections' => array_values($sectionGroups),
            'profiles' => $standaloneProfiles,
            'has_profiles' => $profileKeys !== [],
        ];
    }

    /**
     * @param  array{sections?: array<string, array{enabled?: bool}>, profiles?: array<string, array{enabled?: bool, columns?: list<string>}>}  $input
     * @return array{sections: array<string, array{enabled: bool}>, profiles: array<string, array{enabled: bool, columns: list<string>}>}
     */
    public function normalizeUserVisibility(string $roleSlug, array $input): array
    {
        $profileKeys = $this->profilesForRole($roleSlug);
        $normalized = [
            'sections' => [],
            'profiles' => [],
        ];

        foreach ($this->sections() as $sectionKey => $sectionMeta) {
            $sectionProfiles = array_values(array_intersect($sectionMeta['profiles'] ?? [], $profileKeys));
            if ($sectionProfiles === []) {
                continue;
            }

            $sectionInput = $input['sections'][$sectionKey] ?? [];
            $normalized['sections'][$sectionKey] = [
                'enabled' => filter_var($sectionInput['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        foreach ($profileKeys as $profileKey) {
            $profileInput = $input['profiles'][$profileKey] ?? [];
            $allKeys = $this->allColumnKeys($profileKey);
            $selected = array_values(array_filter(
                is_array($profileInput['columns'] ?? null) ? $profileInput['columns'] : [],
                fn ($key) => is_string($key) && in_array($key, $allKeys, true),
            ));
            $enabled = filter_var($profileInput['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($enabled) {
                $selected = $this->ensureRequiredColumns($profileKey, $selected);
            }

            $normalized['profiles'][$profileKey] = [
                'enabled' => $enabled,
                'columns' => $selected,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{
     *     slug: string,
     *     label_ar: string,
     *     sections: list<array{key: string, label_ar: string, enabled: bool, profiles: list<array<string, mixed>>}>,
     *     profiles: list<array<string, mixed>>
     * }>
     */
    public function catalogForAdmin(): array
    {
        $stored = $this->all();
        $out = [];

        foreach (Role::query()->orderBy('id')->get(['slug', 'label_ar']) as $role) {
            $sectionGroups = [];
            $standaloneProfiles = [];

            foreach ($this->profiles() as $profileKey => $profileMeta) {
                $profilePayload = $this->profilePayloadForAdmin(
                    $role->slug,
                    $profileKey,
                    $profileMeta,
                    $stored,
                );

                $section = $profileMeta['section'] ?? null;
                if ($section !== null && isset($this->sections()[$section])) {
                    if (! isset($sectionGroups[$section])) {
                        $sectionRole = $stored['sections'][$section]['roles'][$role->slug]
                            ?? $this->defaultSectionRole($role->slug, $section);
                        $sectionGroups[$section] = [
                            'key' => $section,
                            'label_ar' => $this->sections()[$section]['label_ar'] ?? $section,
                            'enabled' => (bool) ($sectionRole['enabled'] ?? false),
                            'profiles' => [],
                        ];
                    }
                    $sectionGroups[$section]['profiles'][] = $profilePayload;
                } else {
                    $standaloneProfiles[] = $profilePayload;
                }
            }

            $out[] = [
                'slug' => $role->slug,
                'label_ar' => $role->label_ar,
                'sections' => array_values($sectionGroups),
                'profiles' => $standaloneProfiles,
            ];
        }

        return $out;
    }

    /**
     * @param  array{
     *     sections?: array<string, array{roles?: array<string, array{enabled?: bool}>}>,
     *     roles?: array<string, array<string, array{enabled?: bool, columns?: list<string>}>>
     * }  $input
     */
    public function update(array $input): void
    {
        $normalized = [
            'sections' => [],
            'roles' => [],
        ];

        foreach ($this->sections() as $sectionKey => $sectionMeta) {
            foreach (Role::query()->pluck('slug') as $slug) {
                $sectionInput = $input['sections'][$sectionKey]['roles'][$slug] ?? [];
                $normalized['sections'][$sectionKey]['roles'][$slug] = [
                    'enabled' => filter_var($sectionInput['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }
        }

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

    /**
     * @param  array{label_ar?: string, dashboard?: string, page?: string}  $profileMeta
     * @param  array{sections: array<string, array{roles: array<string, array{enabled: bool}>>>, roles: array<string, array<string, array{enabled: bool, columns: list<string>}>>}  $stored
     * @return array<string, mixed>
     */
    private function profilePayloadForAdmin(string $roleSlug, string $profileKey, array $profileMeta, array $stored): array
    {
        $roleConfig = $stored['roles'][$roleSlug][$profileKey]
            ?? $this->defaultRoleProfile($roleSlug, $profileKey);

        return $this->buildProfilePayload($profileKey, $profileMeta, $roleConfig);
    }

    /**
     * @param  array{enabled: bool, columns: list<string>}  $roleConfig
     * @param  array{label_ar?: string, dashboard?: string, page?: string, section?: string}  $profileMeta
     * @return array<string, mixed>
     */
    private function buildProfilePayload(string $profileKey, array $profileMeta, array $roleConfig): array
    {
        $columns = [];
        foreach ($this->columnDefinitions($profileKey) as $colKey => $colMeta) {
            $columns[] = [
                'key' => $colKey,
                'label' => $colMeta['label'] ?? $colKey,
                'gate' => $colMeta['gate'] ?? null,
                'visible' => in_array($colKey, $roleConfig['columns'] ?? [], true),
            ];
        }

        return [
            'key' => $profileKey,
            'label_ar' => $profileMeta['label_ar'] ?? $profileKey,
            'dashboard' => $profileMeta['dashboard'] ?? '',
            'page' => $profileMeta['page'] ?? '',
            'section' => $profileMeta['section'] ?? null,
            'enabled' => (bool) ($roleConfig['enabled'] ?? false),
            'columns' => $columns,
        ];
    }

    /**
     * @param  array{sections: array<string, array{roles: array<string, array{enabled: bool}>>>, roles: array<string, array<string, array{enabled: bool, columns: list<string>}>>}  $stored
     * @param  array{sections?: array<string, array{enabled?: bool}>, profiles?: array<string, array{enabled?: bool, columns?: list<string>}>}|null  $userStored
     * @return array{enabled: bool}
     */
    private function resolveSectionConfig(string $roleSlug, string $section, ?array $userStored, array $stored): array
    {
        if (is_array($userStored) && isset($userStored['sections'][$section]['enabled'])) {
            return ['enabled' => (bool) $userStored['sections'][$section]['enabled']];
        }

        $raw = $stored['sections'][$section]['roles'][$roleSlug] ?? null;

        if (! is_array($raw)) {
            return $this->defaultSectionRole($roleSlug, $section);
        }

        return ['enabled' => (bool) ($raw['enabled'] ?? false)];
    }

    /**
     * @param  array{sections: array<string, array{roles: array<string, array{enabled: bool}>>>, roles: array<string, array<string, array{enabled: bool, columns: list<string>}>>}  $stored
     * @param  array{sections?: array<string, array{enabled?: bool}>, profiles?: array<string, array{enabled?: bool, columns?: list<string>}>}|null  $userStored
     * @return array{enabled: bool, columns: list<string>}
     */
    private function resolveProfileConfig(string $roleSlug, string $profileKey, ?array $userStored, array $stored): array
    {
        if (is_array($userStored) && isset($userStored['profiles'][$profileKey]) && is_array($userStored['profiles'][$profileKey])) {
            $raw = $userStored['profiles'][$profileKey];

            return [
                'enabled' => (bool) ($raw['enabled'] ?? false),
                'columns' => $this->ensureRequiredColumns(
                    $profileKey,
                    is_array($raw['columns'] ?? null) ? $raw['columns'] : [],
                ),
            ];
        }

        $raw = $stored['roles'][$roleSlug][$profileKey] ?? null;

        if (! is_array($raw)) {
            return $this->defaultRoleProfile($roleSlug, $profileKey);
        }

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'columns' => $this->ensureRequiredColumns(
                $profileKey,
                is_array($raw['columns'] ?? null) ? $raw['columns'] : [],
            ),
        ];
    }

    /** @return array{enabled: bool} */
    private function defaultSectionRole(string $roleSlug, string $section): array
    {
        $sectionMeta = $this->sections()[$section] ?? [];
        $defaultRoles = $sectionMeta['default_roles'] ?? [];

        return [
            'enabled' => in_array($roleSlug, $defaultRoles, true),
        ];
    }

    /** @return array{enabled: bool} */
    private function roleSectionConfig(string $roleSlug, string $section, ?User $user = null): array
    {
        if ($user !== null && is_array($user->catalog_list_visibility)) {
            $raw = $user->catalog_list_visibility['sections'][$section] ?? null;
            if (is_array($raw) && array_key_exists('enabled', $raw)) {
                return ['enabled' => (bool) $raw['enabled']];
            }
        }

        $stored = $this->all();
        $raw = $stored['sections'][$section]['roles'][$roleSlug] ?? null;

        if (! is_array($raw)) {
            return $this->defaultSectionRole($roleSlug, $section);
        }

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
        ];
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
    private function roleProfileConfig(string $roleSlug, string $profile, ?User $user = null): array
    {
        if ($user !== null && is_array($user->catalog_list_visibility)) {
            $raw = $user->catalog_list_visibility['profiles'][$profile] ?? null;
            if (is_array($raw)) {
                return [
                    'enabled' => (bool) ($raw['enabled'] ?? false),
                    'columns' => $this->ensureRequiredColumns(
                        $profile,
                        is_array($raw['columns'] ?? null) ? $raw['columns'] : [],
                    ),
                ];
            }
        }

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
     * @param  array{enabled?: bool}  $storedRole
     * @return array{enabled: bool}
     */
    private function mergeSectionRoleDefault(string $roleSlug, string $section, array $storedRole): array
    {
        if (isset($storedRole['enabled'])) {
            return ['enabled' => (bool) $storedRole['enabled']];
        }

        return $this->defaultSectionRole($roleSlug, $section);
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
