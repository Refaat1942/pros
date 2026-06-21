<?php

namespace App\Services;

use App\Enums\StockStoreClass;
use App\Enums\StockUom;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use Database\Seeders\Support\PrototypeSeedData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * إدارة كatalog الأصناف — إنشاء / تعديل / حذف مع أسعار الموردين.
 */
class StockCatalogService
{
    public function listForDashboard(): Collection
    {
        return StockItem::query()
            ->with(['category:id,name', 'prices.supplier:id,name'])
            ->orderByDesc('id')
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get()
            ->map(fn (StockItem $item) => $this->formatItem($item));
    }

    public function formatItem(StockItem $item): array
    {
        $item->loadMissing(['category:id,name', 'prices.supplier:id,name']);

        return [
            'id'          => $item->id,
            'code'        => $item->code,
            'name'        => $item->name,
            'spec'        => $item->spec,
            'category_id' => $item->category_id,
            'category'    => $item->category?->name ?? '',
            'qty'         => (int) $item->qty,
            'reserved'    => (int) $item->reserved,
            'status'      => $item->status,
            'prices'      => $item->prices->map(fn (StockItemPrice $p) => [
                'id'          => (string) $p->id,
                'label'       => $p->label,
                'supplier_id' => $p->supplier_id,
                'supplier'    => $p->supplier?->name ?? '',
                'itemCode'    => $p->supplier_item_code,
                'amount'      => (float) $p->amount,
            ])->values()->all(),
        ];
    }

    public function create(array $data): StockItem
    {
        return DB::transaction(function () use ($data) {
            $category = StockCategory::find($data['category_id']);
            $code     = $this->nextCode();

            $item = StockItem::create([
                'code'        => $code,
                'name'        => $data['name'],
                'spec'        => $data['spec'] ?? null,
                'category_id' => $data['category_id'],
                'store_class' => $this->deriveStoreClass($category),
                'uom'         => StockUom::Piece->value,
                'barcode'     => PrototypeSeedData::deriveBarcode($code),
                'qty'         => (int) ($data['qty'] ?? 0),
                'reserved'    => 0,
                'status'      => StockItem::STATUS_OK,
            ]);

            $this->syncPrices($item, $data['prices'] ?? []);
            $this->applyInitialWac($item, (int) ($data['qty'] ?? 0), $data['prices'] ?? []);
            $this->syncStatus($item);

            AuditService::log(
                action:      'create',
                description: "إضافة صنف {$item->code} — {$item->name}",
                tag:         'admin',
                after:       $this->formatItem($item->fresh(['category', 'prices.supplier'])),
            );

            return $item->fresh(['category', 'prices.supplier']);
        });
    }

    public function update(StockItem $item, array $data): StockItem
    {
        return DB::transaction(function () use ($item, $data) {
            $before = $this->formatItem($item);

            $item->update([
                'name'        => $data['name'],
                'spec'        => $data['spec'] ?? null,
                'category_id' => $data['category_id'],
                'qty'         => (int) ($data['qty'] ?? $item->qty),
            ]);

            if (isset($data['category_id'])) {
                $category = StockCategory::find($data['category_id']);
                $item->update(['store_class' => $this->deriveStoreClass($category)]);
            }

            $this->syncPrices($item, $data['prices'] ?? []);
            $this->syncStatus($item->fresh());

            AuditService::log(
                action:      'update',
                description: "تعديل صنف {$item->code}",
                tag:         'admin',
                before:      $before,
                after:       $this->formatItem($item->fresh(['category', 'prices.supplier'])),
            );

            return $item->fresh(['category', 'prices.supplier']);
        });
    }

    public function delete(StockItem $item): void
    {
        if ($item->movements()->exists()) {
            throw new \InvalidArgumentException('لا يمكن حذف الصنف — له حركات مخزنية مسجّلة.');
        }

        if (DB::table('bom_items')->where('stock_item_code', $item->code)->exists()) {
            throw new \InvalidArgumentException('لا يمكن حذف الصنف — مرتبط بقائمة مواد.');
        }

        $before = $this->formatItem($item);

        AuditService::log(
            action:      'delete',
            description: "حذف صنف {$item->code} — {$item->name}",
            tag:         'admin',
            before:      $before,
        );

        $item->delete();
    }

    private function nextCode(): string
    {
        $lastNum = StockItem::query()
            ->where('code', 'like', 'ITM-%')
            ->pluck('code')
            ->map(fn (string $code) => (int) preg_replace('/\D/', '', $code))
            ->max();

        $next = ((int) $lastNum) + 1;

        return 'ITM-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function deriveStoreClass(?StockCategory $category): string
    {
        return match ($category?->name) {
            'بطانات'     => StockStoreClass::Consumables->value,
            'إكسسوارات'  => StockStoreClass::Tools->value,
            default      => StockStoreClass::Raw->value,
        };
    }

    private function syncPrices(StockItem $item, array $prices): void
    {
        $keepIds = [];

        foreach ($prices as $index => $row) {
            $supplierId = (int) ($row['supplier_id'] ?? 0);
            $amount     = (float) ($row['amount'] ?? 0);
            $label      = trim((string) ($row['label'] ?? ''));
            $itemCode   = trim((string) ($row['supplier_item_code'] ?? $row['itemCode'] ?? ''));

            if (! $supplierId || $amount <= 0 || $label === '') {
                continue;
            }

            $priceId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
            $payload = [
                'label'              => $label,
                'supplier_id'        => $supplierId,
                'supplier_item_code' => $itemCode !== '' ? $itemCode : null,
                'amount'             => $amount,
                'qty'                => 1,
            ];

            if ($priceId && $existing = $item->prices()->whereKey($priceId)->first()) {
                $existing->update($payload);
                $keepIds[] = $existing->id;
                continue;
            }

            $seq      = $index + 1;
            $priceRef = sprintf('PR-%s-%d', $item->code, $seq);

            $created = $item->prices()->create(array_merge($payload, [
                'price_ref' => $priceRef,
            ]));
            $keepIds[] = $created->id;
        }

        if ($keepIds) {
            $item->prices()->whereNotIn('id', $keepIds)->delete();
        } else {
            $item->prices()->delete();
        }
    }

    private function applyInitialWac(StockItem $item, int $qty, array $prices): void
    {
        if ($qty <= 0 || ! $prices) {
            return;
        }

        $amounts = array_map(fn ($p) => (float) ($p['amount'] ?? 0), $prices);
        $amounts = array_filter($amounts, fn ($a) => $a > 0);

        if ($amounts) {
            $item->update(['wac' => max($amounts)]);
        }
    }

    private function syncStatus(StockItem $item): void
    {
        $status = (int) $item->qty <= StockItem::LOW_QTY_THRESHOLD
            ? StockItem::STATUS_LOW
            : StockItem::STATUS_OK;

        if ($item->status !== $status) {
            $item->update(['status' => $status]);
        }
    }
}
