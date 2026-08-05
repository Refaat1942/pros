<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockKit;
use App\Models\StockKitItem;
use App\Support\StockKitGroups;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockKitService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function pickerRows(): array
    {
        return StockKit::query()
            ->where('is_active', true)
            ->with(['items.stockItem:id,code,name,uom,alt_codes'])
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (StockKit $kit) => $this->formatPickerRow($kit))
            ->filter(fn (array $row) => $row['components'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return list<array{stock_item_code: string, name: string, qty: int, uom?: string, group_label: string}>
     */
    public function expandForSpec(string $kitCode): array
    {
        $kit = $this->findByCode($kitCode);
        if (! $kit) {
            abort(422, 'الطقم غير موجود.');
        }

        return $this->expandedLines($kit);
    }

    public function listForAdmin(): Collection
    {
        return StockKit::query()
            ->with(['items.stockItem:id,code,catalog_number,name,alt_codes,uom,page_number'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (StockKit $kit) => $this->formatAdminRow($kit));
    }

    /**
     * @param  array{name: string, code?: string|null, type?: string, description?: string|null, is_active?: bool, items?: list<array{stock_item_id: int, qty: int}>}  $data
     */
    public function create(array $data): StockKit
    {
        return DB::transaction(function () use ($data) {
            $code = $this->resolveCode($data['code'] ?? null);

            $kit = StockKit::create([
                'code' => $code,
                'name' => trim($data['name']),
                'type' => $this->normalizeType($data['type'] ?? StockKit::TYPE_ASSEMBLY),
                'spec_group' => StockKitGroups::normalizeKey($data['spec_group'] ?? null),
                'description' => $this->nullableString($data['description'] ?? null),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->syncItems($kit, $data['items'] ?? []);

            AuditService::log(
                action: 'create',
                description: "إضافة طقم {$kit->code} — {$kit->name}",
                tag: 'admin',
                after: $this->formatAdminRow($kit->fresh()->load('items.stockItem')),
            );

            return $kit->fresh()->load('items.stockItem');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(StockKit $kit, array $data): StockKit
    {
        return DB::transaction(function () use ($kit, $data) {
            $before = $this->formatAdminRow($kit);

            $kit->update([
                'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $kit->name,
                'type' => array_key_exists('type', $data)
                    ? $this->normalizeType($data['type'])
                    : $kit->type,
                'spec_group' => array_key_exists('spec_group', $data)
                    ? StockKitGroups::normalizeKey($data['spec_group'])
                    : $kit->spec_group,
                'description' => array_key_exists('description', $data)
                    ? $this->nullableString($data['description'])
                    : $kit->description,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $kit->is_active,
            ]);

            if (array_key_exists('items', $data)) {
                $this->syncItems($kit, (array) $data['items']);
            }

            AuditService::log(
                action: 'update',
                description: "تعديل طقم {$kit->code}",
                tag: 'admin',
                before: $before,
                after: $this->formatAdminRow($kit->fresh()->load('items.stockItem')),
            );

            return $kit->fresh()->load('items.stockItem');
        });
    }

    public function delete(StockKit $kit): void
    {
        AuditService::log(
            action: 'delete',
            description: "حذف طقم {$kit->code} — {$kit->name}",
            tag: 'admin',
            before: $this->formatAdminRow($kit),
        );

        $kit->delete();
    }

    public function findByCode(string $code): ?StockKit
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        if (str_starts_with(strtoupper($code), 'KIT:')) {
            $code = substr($code, 4);
        }

        return StockKit::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->with(['items.stockItem'])
            ->first();
    }

    /**
     * @param  list<array{stock_item_id: int, qty?: int}>  $items
     */
    private function syncItems(StockKit $kit, array $items): void
    {
        $kit->items()->delete();

        $sort = 0;
        foreach ($items as $row) {
            $stockItemId = (int) ($row['stock_item_id'] ?? 0);
            $qty = max(1, (int) ($row['qty'] ?? 1));

            if ($stockItemId < 1) {
                continue;
            }

            if (! StockItem::whereKey($stockItemId)->exists()) {
                continue;
            }

            StockKitItem::create([
                'stock_kit_id' => $kit->id,
                'stock_item_id' => $stockItemId,
                'qty' => $qty,
                'sort_order' => $sort++,
            ]);
        }
    }

    private function resolveCode(?string $provided): string
    {
        $provided = strtoupper(trim((string) ($provided ?? '')));

        if ($provided !== '' && ! StockKit::where('code', $provided)->exists()) {
            return $provided;
        }

        for ($i = 0; $i < 500; $i++) {
            $code = 'KIT-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (! StockKit::where('code', $code)->exists()) {
                return $code;
            }
        }

        return 'KIT-'.Str::upper(Str::random(6));
    }

    private function normalizeType(?string $type): string
    {
        $type = trim((string) ($type ?? ''));

        return in_array($type, [StockKit::TYPE_ASSEMBLY, StockKit::TYPE_ACCESSORY], true)
            ? $type
            : StockKit::TYPE_ASSEMBLY;
    }

    /**
     * @return list<array{stock_item_code: string, name: string, qty: int, uom: string, group_label: string}>
     */
    private function expandedLines(StockKit $kit): array
    {
        $kit->loadMissing('items.stockItem');
        $label = StockKitGroups::label($kit->spec_group) ?? $kit->name;

        return $kit->items->map(function (StockKitItem $line) use ($label) {
            $item = $line->stockItem;
            $code = $item?->operationalCode() ?? '';

            if ($code === '') {
                return null;
            }

            return [
                'stock_item_code' => $code,
                'name' => $item->name,
                'qty' => max(1, (int) $line->qty),
                'uom' => $item->uom ?? 'قطعة',
                'group_label' => $label,
            ];
        })->filter()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPickerRow(StockKit $kit): array
    {
        $components = $kit->items->map(function (StockKitItem $line) {
            $item = $line->stockItem;
            $code = $item?->operationalCode() ?? '';
            if ($code === '') {
                return null;
            }

            return [
                'code' => $code,
                'name' => $item->name,
                'qty' => max(1, (int) $line->qty),
                'uom' => $item->uom ?? 'قطعة',
            ];
        })->filter()->values()->all();

        return [
            'code' => 'KIT:'.$kit->code,
            'catalog_code' => $kit->code,
            'name' => $kit->name,
            'type' => 'kit',
            'kit_type' => $kit->type,
            'kit_type_label' => $kit->type === StockKit::TYPE_ACCESSORY ? 'مخصصات' : 'طقم جاهز',
            'spec_group' => $kit->spec_group,
            'spec_group_label' => StockKitGroups::label($kit->spec_group),
            'group_label' => StockKitGroups::label($kit->spec_group),
            'spec' => count($components).' مكوّن',
            'uom' => 'طقم',
            'qty' => 0,
            'reserved' => 0,
            'available_max' => 999,
            'components' => $components,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAdminRow(StockKit $kit): array
    {
        return [
            'id' => $kit->id,
            'code' => $kit->code,
            'name' => $kit->name,
            'type' => $kit->type,
            'type_label' => $kit->type === StockKit::TYPE_ACCESSORY ? 'مخصصات' : 'طقم جاهز',
            'spec_group' => $kit->spec_group,
            'spec_group_label' => StockKitGroups::label($kit->spec_group),
            'description' => $kit->description,
            'is_active' => (bool) $kit->is_active,
            'items' => $kit->items->map(fn (StockKitItem $line) => [
                'id' => $line->id,
                'stock_item_id' => $line->stock_item_id,
                'stock_item_code' => $line->stockItem?->operationalCode() ?? '',
                'code' => $line->stockItem?->operationalCode()
                    ?: ($line->stockItem?->catalog_number ?? $line->stockItem?->code ?? ''),
                'name' => $line->stockItem?->name ?? '—',
                'page_number' => $line->stockItem?->page_number ?? '',
                'uom' => $line->stockItem?->uom ?? 'قطعة',
                'qty' => (int) $line->qty,
            ])->values()->all(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed !== '' ? $trimmed : null;
    }
}
