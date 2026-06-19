<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function create(array $data): User
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'role_id'  => $data['role_id'],
            'status'   => $data['status'] ?? User::STATUS_ACTIVE,
        ]);

        AuditService::log(
            action:      'create',
            description: "إضافة موظف: {$user->name} ({$user->email})",
            tag:         'admin',
            after:       $user->only(['id', 'name', 'email', 'role_id', 'status']),
        );

        return $user->load('role:id,slug,label_ar');
    }

    public function update(User $user, array $data): User
    {
        $before = $user->only(['name', 'email', 'role_id', 'status']);

        $payload = [
            'name'    => $data['name'],
            'email'   => $data['email'],
            'role_id' => $data['role_id'],
            'status'  => $data['status'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);

        AuditService::log(
            action:      'update',
            description: "تعديل موظف: {$user->name}",
            tag:         'admin',
            before:      $before,
            after:       $user->fresh()->only(['name', 'email', 'role_id', 'status']),
        );

        return $user->fresh()->load('role:id,slug,label_ar');
    }

    public function toggleStatus(User $user): User
    {
        $before = $user->only(['status']);

        $user->update([
            'status' => $user->status === User::STATUS_ACTIVE
                ? User::STATUS_INACTIVE
                : User::STATUS_ACTIVE,
        ]);

        AuditService::log(
            action:      'update',
            description: "تبديل حالة موظف: {$user->name} → {$user->status}",
            tag:         'admin',
            before:      $before,
            after:       $user->only(['status']),
        );

        return $user;
    }
}
