<?php

namespace App\Services;

use App\Models\AdjustmentItemGroup;
use App\Models\AdjustmentItemGroupLine;
use App\Models\StockItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdjustmentItemGroupService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForDesk(): Collection
    {
        return AdjustmentItemGroup::query()
            ->with(['lines' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('name')
            ->get()
            ->map(fn (AdjustmentItemGroup $group) => $this->formatGroup($group));
    }

    /**
     * @param  array{name: string, notes?: string|null, items: list<array{stock_item_code: string, qty: float|int|string}>}  $data
     */
    public function create(array $data): AdjustmentItemGroup
    {
        return DB::transaction(function () use ($data) {
            $group = AdjustmentItemGroup::create([
                'name' => trim($data['name']),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'created_by' => auth()->id(),
            ]);

            $this->syncLines($group, $data['items'] ?? []);

            AuditService::log(
                action: 'create',
                description: "إضافة مجموعة معدلات: {$group->name}",
                tag: 'adjustments',
                after: $this->formatGroup($group->fresh()->load('lines')),
            );

            return $group->fresh()->load('lines');
        });
    }

    /**
     * @param  array{name?: string, notes?: string|null, items?: list<array{stock_item_code: string, qty: float|int|string}>}  $data
     */
    public function update(AdjustmentItemGroup $group, array $data): AdjustmentItemGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $before = $this->formatGroup($group->load('lines'));

            if (array_key_exists('name', $data)) {
                $group->name = trim((string) $data['name']);
            }
            if (array_key_exists('notes', $data)) {
                $group->notes = $this->nullableString($data['notes'] ?? null);
            }
            $group->save();

            if (array_key_exists('items', $data)) {
                $this->syncLines($group, $data['items'] ?? []);
            }

            $group = $group->fresh()->load('lines');

            AuditService::log(
                action: 'update',
                description: "تعديل مجموعة معدلات: {$group->name}",
                tag: 'adjustments',
                before: $before,
                after: $this->formatGroup($group),
            );

            return $group;
        });
    }

    public function delete(AdjustmentItemGroup $group): void
    {
        DB::transaction(function () use ($group) {
            $before = $this->formatGroup($group->load('lines'));
            $name = $group->name;
            $group->delete();

            AuditService::log(
                action: 'delete',
                description: "حذف مجموعة معدلات: {$name}",
                tag: 'adjustments',
                before: $before,
            );
        });
    }

    /**
     * @param  list<array{stock_item_code: string, qty: float|int|string}>  $items
     */
    private function syncLines(AdjustmentItemGroup $group, array $items): void
    {
        if ($items === []) {
            abort(422, 'يجب إضافة صنف واحد على الأقل في المجموعة.');
        }

        $group->lines()->delete();

        $order = 0;
        foreach ($items as $row) {
            $code = trim((string) ($row['stock_item_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $stockItem = StockItem::findByOperationalCode($code, true);
            if (! $stockItem) {
                abort(422, "الصنف غير موجود: {$code}");
            }

            $qty = (float) ($row['qty'] ?? 1);
            if ($qty < 0.001) {
                abort(422, "كمية غير صالحة للصنف {$code}");
            }

            AdjustmentItemGroupLine::create([
                'adjustment_item_group_id' => $group->id,
                'stock_item_code' => $stockItem->code,
                'qty' => $qty,
                'sort_order' => $order++,
            ]);
        }

        if ($order === 0) {
            abort(422, 'يجب إضافة صنف واحد على الأقل في المجموعة.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function formatGroup(AdjustmentItemGroup $group): array
    {
        $lines = $group->relationLoaded('lines')
            ? $group->lines
            : $group->lines()->orderBy('sort_order')->orderBy('id')->get();

        return [
            'id' => $group->id,
            'name' => $group->name,
            'notes' => $group->notes,
            'items' => $lines->map(function (AdjustmentItemGroupLine $line) {
                return [
                    'stock_item_code' => $line->stock_item_code,
                    'qty' => (float) $line->qty,
                ];
            })->values()->all(),
            'updated_at' => $group->updated_at?->toIso8601String(),
        ];
    }

    private function nullableString(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
