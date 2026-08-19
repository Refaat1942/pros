<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class UserService
{
    public function create(array $data): User
    {
        $role = Role::query()->findOrFail($data['role_id']);
        $catalogVisibility = $this->resolveCatalogListVisibility($role->slug, $data['catalog_list_visibility'] ?? null);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => $data['password'],
            'role_id' => $data['role_id'],
            'status' => $data['status'] ?? User::STATUS_ACTIVE,
            'catalog_list_visibility' => $catalogVisibility,
        ]);

        AuditService::log(
            action: 'create',
            description: "إضافة موظف: {$user->name} ({$user->username})",
            tag: 'admin',
            after: $user->only(['id', 'name', 'username', 'role_id', 'status', 'catalog_list_visibility']),
        );

        return $user->load('role:id,slug,label_ar');
    }

    public function update(User $user, array $data): User
    {
        $user->loadMissing('role:id,slug');
        $before = $user->only(['name', 'username', 'role_id', 'status', 'catalog_list_visibility']);

        $payload = [
            'name' => $data['name'],
            'username' => $data['username'],
            'status' => $data['status'],
        ];

        $targetRoleSlug = $user->role?->slug ?? '';

        if (in_array($user->role?->slug, [Role::SLUG_ADMIN, Role::SLUG_SUPER_ADMIN], true)) {
            $payload['status'] = User::STATUS_ACTIVE;
        } else {
            $payload['role_id'] = $data['role_id'];
            $targetRole = Role::query()->findOrFail($data['role_id']);
            $targetRoleSlug = $targetRole->slug;
        }

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        if (array_key_exists('catalog_list_visibility', $data)) {
            $payload['catalog_list_visibility'] = $this->resolveCatalogListVisibility(
                $targetRoleSlug,
                $data['catalog_list_visibility'] ?? null,
            );
        }

        $user->update($payload);

        AuditService::log(
            action: 'update',
            description: "تعديل موظف: {$user->name}",
            tag: 'admin',
            before: $before,
            after: $user->fresh()->only(['name', 'username', 'role_id', 'status', 'catalog_list_visibility']),
        );

        return $user->fresh()->load('role:id,slug,label_ar');
    }

    /**
     * @param  array{sections?: array<string, array{enabled?: bool}>, profiles?: array<string, array{enabled?: bool, columns?: list<string>}>}|null  $input
     * @return array<string, mixed>|null
     */
    private function resolveCatalogListVisibility(string $roleSlug, ?array $input): ?array
    {
        $visibility = app(CatalogListVisibilityService::class);

        if ($visibility->profilesForRole($roleSlug) === []) {
            return null;
        }

        if (! is_array($input) || $input === []) {
            $catalog = $visibility->catalogForRole($roleSlug);
            $input = $this->catalogUiToInput($catalog);
        }

        return $visibility->normalizeUserVisibility($roleSlug, $input);
    }

    /**
     * @param  array{sections?: list<array<string, mixed>>, profiles?: list<array<string, mixed>>}  $catalog
     * @return array{sections: array<string, array{enabled: bool}>, profiles: array<string, array{enabled: bool, columns: list<string>}>}
     */
    private function catalogUiToInput(array $catalog): array
    {
        $input = [
            'sections' => [],
            'profiles' => [],
        ];

        foreach ($catalog['sections'] ?? [] as $section) {
            $input['sections'][$section['key']] = [
                'enabled' => (bool) ($section['enabled'] ?? false),
            ];
        }

        $profiles = array_merge(
            $catalog['profiles'] ?? [],
            ...array_map(fn (array $section) => $section['profiles'] ?? [], $catalog['sections'] ?? []),
        );

        foreach ($profiles as $profile) {
            $columns = [];
            foreach ($profile['columns'] ?? [] as $column) {
                if (! empty($column['visible'])) {
                    $columns[] = $column['key'];
                }
            }
            $input['profiles'][$profile['key']] = [
                'enabled' => (bool) ($profile['enabled'] ?? false),
                'columns' => $columns,
            ];
        }

        return $input;
    }

    public function toggleStatus(User $user): User
    {
        $user->loadMissing('role:id,slug');

        if (in_array($user->role?->slug, [Role::SLUG_ADMIN, Role::SLUG_SUPER_ADMIN], true)) {
            throw new \InvalidArgumentException('لا يمكن تعطيل حساب السوبر أدمن أو مسؤول النظام.');
        }

        $before = $user->only(['status']);

        $user->update([
            'status' => $user->status === User::STATUS_ACTIVE
                ? User::STATUS_INACTIVE
                : User::STATUS_ACTIVE,
        ]);

        AuditService::log(
            action: 'update',
            description: "تبديل حالة موظف: {$user->name} → {$user->status}",
            tag: 'admin',
            before: $before,
            after: $user->only(['status']),
        );

        return $user;
    }

    public function delete(User $user): void
    {
        $before = $user->only(['name', 'username', 'role_id', 'status']);

        $user->delete();

        AuditService::log(
            action: 'delete',
            description: "حذف موظف: {$before['name']}",
            tag: 'admin',
            before: $before,
        );
    }
}