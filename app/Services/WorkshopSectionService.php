<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkshopSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkshopSectionService
{
    /** @return list<WorkshopSection> */
    public function listActive(): array
    {
        return WorkshopSection::query()
            ->where('active', true)
            ->with(['technicians:id,name'])
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function listForAdmin(): array
    {
        return WorkshopSection::query()
            ->with(['technicians:id,name,username,role_id'])
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->map(fn (WorkshopSection $s) => $this->format($s))
            ->values()
            ->all();
    }

    /** @param  list<int>  $technicianIds */
    public function create(array $data, array $technicianIds = []): WorkshopSection
    {
        return DB::transaction(function () use ($data, $technicianIds) {
            $section = WorkshopSection::create([
                'name' => $data['name'],
                'code' => $this->uniqueCode($data['code'] ?? $data['name']),
                'sort' => (int) ($data['sort'] ?? ((int) (WorkshopSection::max('sort') ?? 0) + 10)),
                'active' => (bool) ($data['active'] ?? true),
                'description' => $data['description'] ?? null,
            ]);

            $this->syncTechnicians($section, $technicianIds);

            AuditService::log(
                action: 'create',
                description: "إضافة قسم ورشة: {$section->name}",
                tag: 'workshop',
                after: $section->toArray(),
            );

            return $section->load('technicians:id,name,username');
        });
    }

    /** @param  list<int>  $technicianIds */
    public function update(WorkshopSection $section, array $data, ?array $technicianIds = null): WorkshopSection
    {
        return DB::transaction(function () use ($section, $data, $technicianIds) {
            $before = $section->only(['name', 'code', 'sort', 'active', 'description']);

            $section->update([
                'name' => $data['name'] ?? $section->name,
                'code' => isset($data['code']) ? $this->uniqueCode($data['code'], $section->id) : $section->code,
                'sort' => array_key_exists('sort', $data) ? (int) $data['sort'] : $section->sort,
                'active' => array_key_exists('active', $data) ? (bool) $data['active'] : $section->active,
                'description' => array_key_exists('description', $data) ? $data['description'] : $section->description,
            ]);

            if ($technicianIds !== null) {
                $this->syncTechnicians($section, $technicianIds);
            }

            AuditService::log(
                action: 'update',
                description: "تعديل قسم ورشة: {$section->name}",
                tag: 'workshop',
                before: $before,
                after: $section->fresh()->only(['name', 'code', 'sort', 'active', 'description']),
            );

            return $section->fresh()->load('technicians:id,name,username');
        });
    }

    public function delete(WorkshopSection $section): void
    {
        if ($section->cases()->exists()) {
            abort(422, 'لا يمكن حذف القسم — مرتبط بحالات نشطة.');
        }

        $before = $section->only(['name', 'code']);
        $section->delete();

        AuditService::log(
            action: 'delete',
            description: "حذف قسم ورشة: {$before['name']}",
            tag: 'workshop',
            before: $before,
        );
    }

    /** @return list<array{id: int, name: string, username: string}> */
    public function workshopTechnicians(): array
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_WORKSHOP))
            ->orderBy('name')
            ->get(['id', 'name', 'username'])
            ->map(fn (User $u) => $u->only(['id', 'name', 'username']))
            ->values()
            ->all();
    }

    /** @param  list<int>  $technicianIds */
    private function syncTechnicians(WorkshopSection $section, array $technicianIds): void
    {
        $validIds = User::query()
            ->whereIn('id', $technicianIds)
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_WORKSHOP))
            ->pluck('id')
            ->all();

        $section->technicians()->sync($validIds);
    }

    private function uniqueCode(string $raw, ?int $exceptId = null): string
    {
        $base = Str::slug($raw, '_');
        if ($base === '') {
            $base = 'section';
        }

        $code = $base;
        $i = 1;

        while (WorkshopSection::query()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where('code', $code)
            ->exists()) {
            $code = $base.'_'.$i;
            $i++;
        }

        return Str::limit($code, 50, '');
    }

    /** @return array<string, mixed> */
    private function format(WorkshopSection $section): array
    {
        return $section->only(['id', 'name', 'code', 'sort', 'active', 'description']) + [
            'technicians' => $section->relationLoaded('technicians')
                ? $section->technicians->map(fn (User $u) => $u->only(['id', 'name', 'username']))->values()->all()
                : [],
            'technician_ids' => $section->relationLoaded('technicians')
                ? $section->technicians->pluck('id')->all()
                : [],
        ];
    }
}
