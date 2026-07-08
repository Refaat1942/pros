<?php

namespace App\Services;

use App\Enums\StockStoreClass;
use App\Enums\StockUom;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * إدارة كatalog الأصناف — إنشاء / تعديل / حذف مع أسعار الموردين.
 */
class StockCatalogService
{
    public function __construct(private readonly StockCategorySchemaService $categorySchema) {}

    public function listForDashboard(?string $from = null, ?string $to = null): Collection
    {
        $range = $this->parseDateRange($from, $to);

        return StockItem::query()
            ->with([
                'category:id,name',
                'prices:id,stock_item_id,label,amount',
                'attributeValues.field',
                'suppliers:id,name',
            ])
            ->when($range['from'], fn ($q, Carbon $start) => $q->where('created_at', '>=', $start))
            ->when($range['to'], fn ($q, Carbon $end) => $q->where('created_at', '<=', $end))
            ->orderByDesc('id')
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get()
            ->map(fn (StockItem $item) => $this->formatItem($item));
    }

    /** @return array{from: Carbon|null, to: Carbon|null} */
    public function parseDateRange(?string $from, ?string $to): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        if ($fromDate && $toDate && $fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        return ['from' => $fromDate, 'to' => $toDate];
    }

    public function formatItem(StockItem $item): array
    {
        $item->loadMissing(['category:id,name', 'prices:id,stock_item_id,label,amount', 'attributeValues.field', 'suppliers:id,name']);

        return [
            'id' => $item->id,
            'code' => $item->code,
            'barcode' => $item->barcode,
            'name' => $item->name,
            'spec' => $item->spec,
            'category_id' => $item->category_id,
            'category' => $item->category?->name ?? '',
            'is_quick_dispense' => (bool) $item->is_quick_dispense,
            'uom' => $item->uom,
            'attributes' => $this->categorySchema->formatItemAttributes($item),
            'attributes_map' => collect($this->categorySchema->formatItemAttributes($item))
                ->mapWithKeys(fn (array $row) => [$row['field_key'] => $row['value']])
                ->all(),
            'qty' => (int) $item->qty,
            'reserved' => (int) $item->reserved,
            'min_qty' => (int) ($item->min_qty ?? 0),
            'price' => (float) $item->price,
            'highest_price' => $this->highestPrice($item),
            'expiry_date' => $item->expiry_date?->toDateString(),
            'wac' => (float) $item->wac,
            'status' => $item->status,
            'created_at' => $item->created_at?->toDateString(),
            'updated_at' => $item->updated_at?->toDateString(),
            // الأسعار الإضافية (إن وُجدت — صنف بأكثر من سعر).
            'prices' => $item->prices->map(fn (StockItemPrice $p) => [
                'id' => (string) $p->id,
                'label' => $p->label,
                'amount' => (float) $p->amount,
            ])->values()->all(),
            'suppliers' => $item->suppliers->map(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
            ])->values()->all(),
        ];
    }

    public function create(array $data): StockItem
    {
        return DB::transaction(function () use ($data) {
            $code = trim((string) ($data['code'] ?? '')) !== '' ? trim((string) $data['code']) : $this->nextCode();
            $category = ! empty($data['category_id']) ? StockCategory::find($data['category_id']) : null;
            $qty = (int) ($data['qty'] ?? 0);
            $price = (float) ($data['price'] ?? 0);

            $item = StockItem::create([
                'code' => $code,
                'name' => $data['name'],
                'spec' => $data['spec'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'store_class' => $this->deriveStoreClass($category),
                'is_quick_dispense' => (bool) ($data['is_quick_dispense'] ?? false),
                'uom' => $this->normalizeUom($data['uom'] ?? null),
                'barcode' => 'BC-'.$code,
                'qty' => $qty,
                'reserved' => 0,
                'min_qty' => max(0, (int) ($data['min_qty'] ?? 0)),
                'price' => $price,
                'expiry_date' => $data['expiry_date'] ?? null,
                'wac' => $qty > 0 ? $price : 0,
                'status' => StockItem::STATUS_OK,
            ]);

            // أسعار إضافية (صنف بأكثر من سعر).
            if (! empty($data['prices'])) {
                $this->syncPrices($item, $data['prices']);
            }

            $this->syncStatus($item);

            $this->categorySchema->syncItemAttributes(
                $item,
                isset($data['category_id']) ? (int) $data['category_id'] : null,
                (array) ($data['attributes'] ?? []),
            );

            if (! empty($data['supplier_ids'])) {
                $this->syncSuppliers($item, $data['supplier_ids']);
            }

            AuditService::log(
                action: 'create',
                description: "إضافة صنف {$item->code} — {$item->name}",
                tag: 'admin',
                after: $this->formatItem($item->fresh(['category', 'prices', 'attributeValues.field'])),
            );

            return $item->fresh(['category', 'prices', 'attributeValues.field', 'suppliers']);
        });
    }

    public function update(StockItem $item, array $data): StockItem
    {
        return DB::transaction(function () use ($item, $data) {
            $before = $this->formatItem($item);
            $price = array_key_exists('price', $data) ? (float) $data['price'] : (float) $item->price;

            $item->update([
                'name' => $data['name'],
                'spec' => $data['spec'] ?? $item->spec,
                'uom' => array_key_exists('uom', $data) && trim((string) $data['uom']) !== ''
                    ? $this->normalizeUom($data['uom'])
                    : $item->uom,
                'qty' => (int) ($data['qty'] ?? $item->qty),
                'min_qty' => array_key_exists('min_qty', $data)
                    ? max(0, (int) $data['min_qty'])
                    : (int) ($item->min_qty ?? 0),
                'price' => $price,
                'expiry_date' => $data['expiry_date'] ?? $item->expiry_date,
                'is_quick_dispense' => array_key_exists('is_quick_dispense', $data)
                    ? (bool) $data['is_quick_dispense']
                    : (bool) $item->is_quick_dispense,
            ]);

            if (! empty($data['category_id'])) {
                $category = StockCategory::find($data['category_id']);
                $item->update([
                    'category_id' => $data['category_id'],
                    'store_class' => $this->deriveStoreClass($category),
                ]);
            }

            if (array_key_exists('attributes', $data)) {
                $this->categorySchema->syncItemAttributes(
                    $item,
                    (int) ($data['category_id'] ?? $item->category_id),
                    (array) $data['attributes'],
                );
            }

            if (array_key_exists('prices', $data)) {
                $this->syncPrices($item, $data['prices'] ?? []);
            }

            if (array_key_exists('supplier_ids', $data)) {
                $this->syncSuppliers($item, $data['supplier_ids'] ?? []);
            }

            $this->syncStatus($item->fresh());

            AuditService::log(
                action: 'update',
                description: "تعديل صنف {$item->code}",
                tag: 'admin',
                before: $before,
                after: $this->formatItem($item->fresh(['category', 'prices', 'attributeValues.field'])),
            );

            return $item->fresh(['category', 'prices', 'attributeValues.field', 'suppliers']);
        });
    }

    /** @param  list<int>  $supplierIds */
    public function syncSuppliers(StockItem $item, array $supplierIds): void
    {
        $ids = Supplier::query()
            ->whereIn('id', $supplierIds)
            ->pluck('id')
            ->all();

        $item->suppliers()->sync($ids);
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
            action: 'delete',
            description: "حذف صنف {$item->code} — {$item->name}",
            tag: 'admin',
            before: $before,
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

        return 'ITM-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * وحدة القياس: نص حر مسموح، والافتراضي «قطعة» عند الفراغ.
     */
    private function normalizeUom(?string $uom): string
    {
        $uom = trim((string) $uom);

        return $uom !== '' ? $uom : StockUom::Piece->value;
    }

    private function deriveStoreClass(?StockCategory $category): string
    {
        return match ($category?->name) {
            'بطانات' => StockStoreClass::Consumables->value,
            'إكسسوارات' => StockStoreClass::Tools->value,
            default => StockStoreClass::Raw->value,
        };
    }

    /**
     * مزامنة الأسعار الإضافية للصنف (صنف بأكثر من سعر) — سعر + تسمية اختيارية.
     *
     * @param  array<int, array{id?:mixed, label?:string, amount?:mixed}>  $prices
     */
    private function syncPrices(StockItem $item, array $prices): void
    {
        $keepIds = [];

        foreach ($prices as $index => $row) {
            $amount = (float) ($row['amount'] ?? 0);
            $label = trim((string) ($row['label'] ?? ''));

            if ($amount <= 0) {
                continue;
            }

            $priceId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
            $payload = [
                'label' => $label !== '' ? $label : null,
                'amount' => $amount,
                'qty' => 1,
            ];

            if ($priceId && $existing = $item->prices()->whereKey($priceId)->first()) {
                $existing->update($payload);
                $keepIds[] = $existing->id;

                continue;
            }

            $created = $item->prices()->create(array_merge($payload, [
                'price_ref' => sprintf('PR-%s-%d', $item->code, $index + 1),
            ]));
            $keepIds[] = $created->id;
        }

        if ($keepIds) {
            $item->prices()->whereNotIn('id', $keepIds)->delete();
        } else {
            $item->prices()->delete();
        }
    }

    private function syncStatus(StockItem $item): void
    {
        $item->refresh();
        $item->recalculateAndSaveStatus();
    }

    private function highestPrice(StockItem $item): float
    {
        $amounts = [(float) $item->price];

        foreach ($item->prices as $price) {
            $amounts[] = (float) $price->amount;
        }

        return $amounts ? max($amounts) : 0.0;
    }
}
