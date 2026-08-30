<?php

namespace App\Services;

use App\Enums\StockStoreClass;
use App\Enums\StockUom;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Models\Supplier;
use App\Models\User;
use App\Support\StockCatalogPicker;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * إدارة كatalog الأصناف — إنشاء / تعديل / حذف مع أسعار الموردين.
 */
class StockCatalogService
{
    public function __construct(private readonly StockCategorySchemaService $categorySchema) {}

    /** حد موحّد لقوائم الأصناف في كل اللوحات (رفع السوبر أدمن → نفس القائمة everywhere). */
    public function catalogListLimit(): int
    {
        return (int) config('catalog.list_limit', 10000);
    }

    /** @return Builder<StockItem> */
    public function unifiedListQuery(): Builder
    {
        return StockItem::query()
            ->with('category:id,name')
            ->orderBy('code');
    }

    /** @return Collection<int, StockItem> */
    public function allItemsForInventoryOverview(): Collection
    {
        $query = StockItem::query()
            ->with([
                'category:id,name',
                'prices' => fn ($q) => $q->orderByDesc('received_at')->orderByDesc('id'),
                'attributeValues.field',
            ])
            ->orderBy('code');

        $limit = $this->catalogListLimit();
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /** @return Collection<int, StockItem> */
    public function allItemsForUnifiedLists(): Collection
    {
        $query = $this->unifiedListQuery();
        $limit = $this->catalogListLimit();
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function displayCatalogCode(StockItem $item): string
    {
        $catalog = trim((string) ($item->catalog_number ?? ''));

        return $catalog !== '' ? $catalog : $item->code;
    }

    /** @return array<string, mixed> */
    public function formatOperationalListRow(StockItem $item): array
    {
        return [
            'id' => $item->id,
            'code' => $this->displayCatalogCode($item),
            'catalog_number' => $item->catalog_number ?? $item->code,
            'internal_code' => $item->code,
            'operational_code' => $item->operationalCode() ?? '',
            'picker_code' => $item->pickerCode(),
            'name' => $item->name,
            'brand' => $item->brand ?? '',
            'spec' => $item->spec ?? '',
            'uom' => $item->uom ?? '',
            'category' => $item->category?->name ?? '',
            'category_id' => $item->category_id,
            'qty' => (int) $item->qty,
            'reserved' => (int) $item->reserved,
            'min_qty' => (int) ($item->min_qty ?? 0),
            'available' => $item->availableQty(),
            'backorder' => $item->backorderQty(),
            'status' => $item->isBackorder() ? 'backorder' : $item->status,
            'barcode' => $item->barcode,
            'page_number' => $item->page_number ?? '',
            'alt_codes' => $item->alt_codes ?? '',
            'last_moved_at' => $item->last_moved_at?->format('d/m/Y'),
        ];
    }

    /**
     * قائمة المخزن/الإنتاج — نفس أصناف كتالوج السوبر أدمن بدون أسعار.
     *
     * @return list<array<string, mixed>>
     */
    public function listForTechnicalInventory(?User $user = null, string $profile = 'technical_inventory'): array
    {
        $visibility = app(CatalogListVisibilityService::class);

        return $this->allItemsForUnifiedLists()
            ->map(function (StockItem $item) use ($visibility, $user, $profile) {
                $row = $this->formatOperationalListRow($item);

                return $user instanceof User
                    ? $visibility->filterItemFields($row, $user, $profile)
                    : $row;
            })
            ->values()
            ->all();
    }

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
            ->when(
                ($limit = (int) config('catalog.list_limit', 10000)) > 0,
                fn ($q) => $q->limit($limit),
            )
            ->get()
            ->map(fn (StockItem $item) => $this->formatItem($item));
    }

    /** كل الأصناف للتصدير — بدون حد افتراضي. */
    public function listForExport(?string $from = null, ?string $to = null): Collection
    {
        $range = $this->parseDateRange($from, $to);

        return StockItem::query()
            ->when($range['from'], fn ($q, Carbon $start) => $q->where('created_at', '>=', $start))
            ->when($range['to'], fn ($q, Carbon $end) => $q->where('created_at', '<=', $end))
            ->orderBy('id')
            ->get()
            ->map(fn (StockItem $item) => $this->formatItem($item));
    }

    public function countAll(?string $from = null, ?string $to = null): int
    {
        $range = $this->parseDateRange($from, $to);

        return StockItem::query()
            ->when($range['from'], fn ($q, Carbon $start) => $q->where('created_at', '>=', $start))
            ->when($range['to'], fn ($q, Carbon $end) => $q->where('created_at', '<=', $end))
            ->count();
    }

    /** عدد الأصناف بلا كود تشغيلي — الأكواد تُرفع يدوياً من Excel فقط. */
    public function countMissingOperationalCodes(): int
    {
        return StockItem::query()
            ->where(function ($q) {
                $q->whereNull('alt_codes')->orWhere('alt_codes', '');
            })
            ->count();
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
            'catalog_number' => $item->catalog_number ?? $item->code,
            'operational_code' => $item->operationalCode() ?? '',
            'picker_code' => $item->pickerCode(),
            'page_number' => $item->page_number ?? '',
            'barcode' => $item->barcode,
            'alt_codes' => $item->alt_codes ?? '',
            'display_barcode' => $item->displayBarcode(),
            'has_scannable_barcode' => $item->displayBarcode() !== null,
            'name' => $item->name,
            'brand' => $item->brand ?? '',
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
            'opening_qty' => (int) ($item->opening_qty ?? 0),
            'addition' => (int) ($item->addition ?? 0),
            'discount' => (int) ($item->discount ?? 0),
            'catalog_balance' => $item->catalogBalance(),
            'warehouse_qty' => (int) $item->qty,
            'balance' => $item->catalogBalance(),
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
                'qty' => (float) $p->qty,
                'from_supply' => $p->supply_request_line_id !== null,
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
            $catalogNumber = $this->nullableString($data['catalog_number'] ?? null);
            $requestedCode = trim((string) ($data['code'] ?? ''));
            if ($catalogNumber === null && $requestedCode !== '') {
                $catalogNumber = $requestedCode;
            }

            $code = $this->resolveInternalItemCode($requestedCode, $catalogNumber, $data['page_number'] ?? null);
            $operationalCode = $this->resolveOperationalCode($data['alt_codes'] ?? null);
            $category = ! empty($data['category_id']) ? StockCategory::find($data['category_id']) : null;
            $openingQty = (int) ($data['opening_qty'] ?? $data['qty'] ?? 0);
            $addition = (int) ($data['addition'] ?? 0);
            $discount = (int) ($data['discount'] ?? 0);
            $qty = array_key_exists('balance', $data)
                ? (int) $data['balance']
                : (array_key_exists('qty', $data) ? (int) $data['qty'] : ($openingQty + $addition - $discount));
            $price = (float) ($data['price'] ?? 0);

            $item = StockItem::create([
                'code' => $code,
                'catalog_number' => $catalogNumber,
                'page_number' => $this->nullableString($data['page_number'] ?? null),
                'name' => $data['name'],
                'brand' => $this->nullableString($data['brand'] ?? null),
                'spec' => $data['spec'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'store_class' => $this->deriveStoreClass($category),
                'is_quick_dispense' => (bool) ($data['is_quick_dispense'] ?? false),
                'uom' => $this->normalizeUom($data['uom'] ?? null),
                'barcode' => $this->barcodeForOperational($operationalCode),
                'alt_codes' => $operationalCode,
                'qty' => $qty,
                'opening_qty' => $openingQty,
                'addition' => $addition,
                'discount' => $discount,
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

            StockCatalogPicker::forgetCachedRows();

            return $item->fresh(['category', 'prices', 'attributeValues.field', 'suppliers']);
        });
    }

    public function update(StockItem $item, array $data): StockItem
    {
        return DB::transaction(function () use ($item, $data) {
            $before = $this->formatItem($item);
            $price = array_key_exists('price', $data) ? (float) $data['price'] : (float) $item->price;
            $openingQty = array_key_exists('opening_qty', $data)
                ? (int) $data['opening_qty']
                : (int) ($item->opening_qty ?? 0);
            $addition = array_key_exists('addition', $data) ? (int) $data['addition'] : (int) ($item->addition ?? 0);
            $discount = array_key_exists('discount', $data) ? (int) $data['discount'] : (int) ($item->discount ?? 0);
            $qty = array_key_exists('balance', $data)
                ? (int) $data['balance']
                : (array_key_exists('qty', $data)
                    ? (int) $data['qty']
                    : ($openingQty + $addition - $discount));

            $operationalCode = array_key_exists('alt_codes', $data)
                ? $this->resolveOperationalCode($data['alt_codes'], $item)
                : $item->operationalCode();

            $item->update([
                'page_number' => array_key_exists('page_number', $data)
                    ? $this->nullableString($data['page_number'])
                    : $item->page_number,
                'catalog_number' => array_key_exists('catalog_number', $data)
                    ? $this->nullableString($data['catalog_number'])
                    : $item->catalog_number,
                'name' => $data['name'],
                'brand' => array_key_exists('brand', $data)
                    ? $this->nullableString($data['brand'])
                    : $item->brand,
                'spec' => $data['spec'] ?? $item->spec,
                'uom' => array_key_exists('uom', $data) && trim((string) $data['uom']) !== ''
                    ? $this->normalizeUom($data['uom'])
                    : $item->uom,
                'alt_codes' => $operationalCode,
                'barcode' => $this->barcodeForOperational($operationalCode),
                'qty' => $qty,
                'opening_qty' => $openingQty,
                'addition' => $addition,
                'discount' => $discount,
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

            StockCatalogPicker::forgetCachedRows();

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

        if (DB::table('bom_items')->where('stock_item_code', $item->operationalCode())->exists()) {
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

    /** رقم الصنف في الكatalog (ITM-001) — ليس كود الصنف التشغيلي. */
    private function nextCatalogCode(): string
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
     * يُعيد كوداً تشغيلياً صالحاً — بدون توليد تلقائي؛ يُمرَّر من الرفع الجماعي أو النموذج.
     */
    public function resolveOperationalCode(?string $provided, ?StockItem $except = null): ?string
    {
        $provided = trim((string) ($provided ?? ''));

        if ($provided === '') {
            return null;
        }

        if (strlen($provided) > 500) {
            throw new \InvalidArgumentException('كود الصنف (الأكواد) طويل جداً (الحد الأقصى 500 حرف).');
        }

        $exists = StockItem::query()
            ->where('alt_codes', $provided)
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException("كود الصنف {$provided} مستخدم مسبقاً.");
        }

        return $provided;
    }

    /** كود داخلي فريد — يُستخدم عند تكرار رقم الصنف في Excel. */
    private function resolveInternalItemCode(string $requestedCode, ?string $catalogNumber, mixed $pageNumber): string
    {
        $requestedCode = trim($requestedCode);
        $catalogNumber = trim((string) ($catalogNumber ?? ''));
        $pageNumber = trim((string) ($pageNumber ?? ''));

        if ($requestedCode !== '' && ! StockItem::where('code', $requestedCode)->exists()) {
            return $requestedCode;
        }

        if ($catalogNumber !== '' && ! StockItem::where('code', $catalogNumber)->exists()) {
            return $catalogNumber;
        }

        if ($pageNumber !== '' && ! StockItem::where('code', $pageNumber)->exists()) {
            return $pageNumber;
        }

        return $this->nextCatalogCode();
    }

    private function barcodeForOperational(?string $operationalCode): ?string
    {
        return $operationalCode !== null && $operationalCode !== ''
            ? StockItem::barcodeForOperationalCode($operationalCode)
            : null;
    }

    private function nextCode(): string
    {
        return $this->nextCatalogCode();
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

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * يُوحّد حقول الكتالوج (أول/إضافة/خصم) مع رصيد المخزن للأصناف بلا حركات مخزنية.
     * مفيد بعد استيراد Excel حيث رصيد الكتالوج ≠ qty.
     *
     * @return array{synced: int, skipped: int}
     */
    public function reconcileCatalogLedgerFromWarehouse(): array
    {
        $synced = 0;
        $skipped = 0;

        StockItem::query()
            ->withCount('movements')
            ->orderBy('id')
            ->chunkById(200, function ($items) use (&$synced, &$skipped) {
                foreach ($items as $item) {
                    if ($item->movements_count > 0) {
                        $skipped++;

                        continue;
                    }

                    if ($item->catalogBalance() === (int) $item->qty) {
                        $skipped++;

                        continue;
                    }

                    $qty = max(0, (int) $item->qty);
                    $item->update([
                        'opening_qty' => $qty,
                        'addition' => 0,
                        'discount' => 0,
                    ]);
                    $synced++;
                }
            });

        return compact('synced', 'skipped');
    }
}
