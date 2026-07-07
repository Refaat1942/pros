<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class UserService
{
    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => $data['password'],
            'role_id' => $data['role_id'],
            'status' => $data['status'] ?? User::STATUS_ACTIVE,
        ]);

        AuditService::log(
            action: 'create',
            description: "إضافة موظف: {$user->name} ({$user->username})",
            tag: 'admin',
            after: $user->only(['id', 'name', 'username', 'role_id', 'status']),
        );

        return $user->load('role:id,slug,label_ar');
    }

    public function update(User $user, array $data): User
    {
        $user->loadMissing('role:id,slug');
        $before = $user->only(['name', 'username', 'role_id', 'status']);

        $payload = [
            'name' => $data['name'],
            'username' => $data['username'],
            'status' => $data['status'],
        ];

        if ($user->role?->slug === Role::SLUG_ADMIN) {
            $payload['status'] = User::STATUS_ACTIVE;
        } else {
            $payload['role_id'] = $data['role_id'];
        }

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);

        AuditService::log(
            action: 'update',
            description: "تعديل موظف: {$user->name}",
            tag: 'admin',
            before: $before,
            after: $user->fresh()->only(['name', 'username', 'role_id', 'status']),
        );

        return $user->fresh()->load('role:id,slug,label_ar');
    }

    public function toggleStatus(User $user): User
    {
        $user->loadMissing('role:id,slug');

        if ($user->role?->slug === Role::SLUG_ADMIN) {
            throw new \InvalidArgumentException('لا يمكن تعطيل حساب مسؤول النظام.');
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
