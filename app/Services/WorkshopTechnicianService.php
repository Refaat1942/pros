<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Support\UsernameRules;
use App\Models\WorkshopSection;
use Illuminate\Support\Facades\DB;

class WorkshopTechnicianService
{
    /** @return list<array<string, mixed>> */
    public function listForAdmin(): array
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_WORKSHOP))
            ->with(['workshopSections:id,name,code'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->format($u))
            ->values()
            ->all();
    }

    /** @param  list<int>  $sectionIds */
    public function create(array $data, array $sectionIds = []): User
    {
        $roleId = Role::query()->where('slug', Role::SLUG_WORKSHOP)->value('id');

        if (! $roleId) {
            abort(422, 'دور قسم الإنتاج غير مُعرَّف في النظام.');
        }

        return DB::transaction(function () use ($data, $sectionIds, $roleId) {
            $user = User::create([
                'name' => $data['name'],
                'username' => UsernameRules::normalize($data['username']),
                'password' => $data['password'],
                'role_id' => $roleId,
                'status' => $data['status'] ?? User::STATUS_ACTIVE,
            ]);

            $this->syncSections($user, $sectionIds);

            AuditService::log(
                action: 'create',
                description: "إضافة فني ورشة: {$user->name} ({$user->username})",
                tag: 'workshop',
                after: $user->only(['id', 'name', 'username', 'status']),
            );

            return $user->fresh()->load(['workshopSections:id,name,code']);
        });
    }

    /** @param  list<int>|null  $sectionIds */
    public function update(User $user, array $data, ?array $sectionIds = null): User
    {
        $this->assertWorkshopTechnician($user);

        return DB::transaction(function () use ($user, $data, $sectionIds) {
            $before = $user->only(['name', 'username', 'status']);

            $payload = [
                'name' => $data['name'] ?? $user->name,
                'username' => isset($data['username']) ? UsernameRules::normalize($data['username']) : $user->username,
                'status' => $data['status'] ?? $user->status,
            ];

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $user->update($payload);

            if ($sectionIds !== null) {
                $this->syncSections($user, $sectionIds);
            }

            AuditService::log(
                action: 'update',
                description: "تعديل فني ورشة: {$user->name}",
                tag: 'workshop',
                before: $before,
                after: $user->fresh()->only(['name', 'username', 'status']),
            );

            return $user->fresh()->load(['workshopSections:id,name,code']);
        });
    }

    public function delete(User $user): void
    {
        $this->assertWorkshopTechnician($user);

        if ($user->assignedWorkshopCases()->exists()) {
            abort(422, 'لا يمكن حذف الفني — مرتبط بحالات إنتاج.');
        }

        $before = $user->only(['name', 'username', 'status']);
        $user->workshopSections()->detach();
        $user->delete();

        AuditService::log(
            action: 'delete',
            description: "حذف فني ورشة: {$before['name']}",
            tag: 'workshop',
            before: $before,
        );
    }

    /** @param  list<int>  $sectionIds */
    private function syncSections(User $user, array $sectionIds): void
    {
        $validIds = WorkshopSection::query()
            ->whereIn('id', $sectionIds)
            ->pluck('id')
            ->all();

        $user->workshopSections()->sync($validIds);
    }

    private function assertWorkshopTechnician(User $user): void
    {
        $user->loadMissing('role:id,slug');

        if ($user->role?->slug !== Role::SLUG_WORKSHOP) {
            abort(404);
        }
    }

    /** @return array<string, mixed> */
    private function format(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'status' => $user->status,
            'active' => $user->status === User::STATUS_ACTIVE,
            'sections' => $user->relationLoaded('workshopSections')
                ? $user->workshopSections->map(fn (WorkshopSection $s) => $s->only(['id', 'name', 'code']))->values()->all()
                : [],
            'section_ids' => $user->relationLoaded('workshopSections')
                ? $user->workshopSections->pluck('id')->all()
                : [],
        ];
    }
}
