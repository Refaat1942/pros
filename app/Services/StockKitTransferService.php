<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockKit;
use App\Models\StockKitItem;
use App\Support\StockKitGroups;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نقل «عمل المجموعات» بين السيرفرات (مثلاً من الأونلاين للأوفلاين).
 * الأصناف تُربط بالكود (ثم الباركود ثم رقم الصنف) لأن أرقام الـ id تختلف من سيرفر لآخر.
 */
class StockKitTransferService
{
    public const FORMAT = 'prosthetics-stock-kits';

    /** @return array<string, mixed> */
    public function export(): array
    {
        $kits = StockKit::query()
            ->with('items.stockItem:id,code,catalog_number,barcode,name')
            ->orderBy('code')
            ->get()
            ->map(fn (StockKit $kit) => [
                'code' => $kit->code,
                'name' => $kit->name,
                'type' => $kit->type,
                'spec_group' => $kit->spec_group,
                'description' => $kit->description,
                'is_active' => (bool) $kit->is_active,
                'items' => $kit->items
                    ->filter(fn (StockKitItem $line) => $line->stockItem !== null)
                    ->map(fn (StockKitItem $line) => [
                        'item_code' => $line->stockItem->code,
                        'barcode' => $line->stockItem->barcode,
                        'catalog_number' => $line->stockItem->catalog_number,
                        'name' => $line->stockItem->name,
                        'qty' => (int) $line->qty,
                    ])->values()->all(),
            ])->values()->all();

        return [
            'format' => self::FORMAT,
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'source' => config('app.url'),
            'groups' => StockKitGroups::all(),
            'kits' => $kits,
        ];
    }

    /**
     * يضيف المجموعات الجديدة ويحدّث الموجودة بنفس الكود (يستبدل مكوّناتها). لا يحذف شيئاً غير موجود في الملف.
     *
     * @param  array<string, mixed>  $data
     * @return array{created: list<string>, updated: list<string>, skipped: list<array{code: string, reason: string}>, missing_items: list<array{kit: string, item_code: string, name: string}>, groups: int}
     */
    public function import(array $data): array
    {
        if (($data['format'] ?? null) !== self::FORMAT || ! is_array($data['kits'] ?? null)) {
            throw ValidationException::withMessages(['file' => 'الملف ليس ملف «عمل المجموعات» صادر من البرنامج.']);
        }

        $report = ['created' => [], 'updated' => [], 'skipped' => [], 'missing_items' => [], 'groups' => 0];

        DB::transaction(function () use ($data, &$report) {
            foreach ((array) ($data['groups'] ?? []) as $key => $meta) {
                if (is_string($key) && is_array($meta) && trim((string) ($meta['label'] ?? '')) !== '') {
                    StockKitGroups::upsertGroup($key, $meta);
                    $report['groups']++;
                }
            }

            foreach ($data['kits'] as $row) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                $name = trim((string) ($row['name'] ?? ''));
                if ($code === '' || $name === '') {
                    $report['skipped'][] = ['code' => $code ?: '—', 'reason' => 'بدون كود أو اسم'];

                    continue;
                }

                $lines = [];
                foreach ((array) ($row['items'] ?? []) as $item) {
                    $stockItem = $this->findItem($item);
                    if (! $stockItem) {
                        $report['missing_items'][] = [
                            'kit' => $code,
                            'item_code' => (string) ($item['item_code'] ?? ''),
                            'name' => (string) ($item['name'] ?? ''),
                        ];

                        continue;
                    }
                    $lines[] = ['stock_item_id' => $stockItem->id, 'qty' => max(1, (int) ($item['qty'] ?? 1))];
                }

                if ($lines === []) {
                    $report['skipped'][] = ['code' => $code, 'reason' => 'ولا صنف من مكوّناتها موجود في هذا السيرفر'];

                    continue;
                }

                $kit = StockKit::query()->where('code', $code)->first();
                $attrs = [
                    'name' => $name,
                    'type' => in_array($row['type'] ?? '', [StockKit::TYPE_ASSEMBLY, StockKit::TYPE_ACCESSORY], true)
                        ? $row['type'] : StockKit::TYPE_ASSEMBLY,
                    'spec_group' => StockKitGroups::normalizeKey($row['spec_group'] ?? null),
                    'description' => trim((string) ($row['description'] ?? '')) ?: null,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ];

                if ($kit) {
                    $kit->update($attrs);
                    $report['updated'][] = $code;
                } else {
                    $kit = StockKit::create(['code' => $code] + $attrs);
                    $report['created'][] = $code;
                }

                $kit->items()->delete();
                foreach ($lines as $sort => $line) {
                    StockKitItem::create($line + ['stock_kit_id' => $kit->id, 'sort_order' => $sort]);
                }
            }

            AuditService::log(
                action: 'import',
                description: sprintf('استيراد عمل المجموعات: %d جديدة، %d محدّثة', count($report['created']), count($report['updated'])),
                tag: 'admin',
                after: $report,
            );
        });

        return $report;
    }

    /** @param  array<string, mixed>  $report */
    public function summary(array $report): string
    {
        $text = sprintf('تم الاستيراد: %d مجموعة جديدة، %d محدّثة', count($report['created']), count($report['updated']));
        if ($report['skipped'] !== []) {
            $text .= sprintf('، %d اتخطّت', count($report['skipped']));
        }
        if ($report['missing_items'] !== []) {
            $text .= sprintf(' — %d صنف غير موجود في هذا السيرفر (اتشال من المجموعة)', count($report['missing_items']));
        }

        return $text.'.';
    }

    /** @param  array<string, mixed>  $item */
    private function findItem(array $item): ?StockItem
    {
        foreach (['item_code' => 'code', 'barcode' => 'barcode', 'catalog_number' => 'catalog_number'] as $key => $column) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $found = StockItem::query()->where($column, $value)->first();
            if ($found) {
                return $found;
            }
        }

        return null;
    }
}
