<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * بطاقة الصنف الرئيسية — clinic_stock_catalog
 */
class StockItem extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_LOW = 'low';

    public const LOW_QTY_THRESHOLD = 3;

    protected $fillable = [
        'code',
        'catalog_number',
        'page_number',
        'name',
        'brand',
        'spec',
        'category_id',
        'store_class',
        'is_quick_dispense',
        'uom',
        'barcode',
        'alt_codes',
        'qty',
        'opening_qty',
        'addition',
        'discount',
        'reserved',
        'min_qty',
        'price',
        'expiry_date',
        'wac',
        'status',
        'last_moved_at',
        'last_return_ref',
    ];

    protected $casts = [
        'qty' => 'integer',
        'opening_qty' => 'integer',
        'addition' => 'integer',
        'discount' => 'integer',
        'reserved' => 'integer',
        'min_qty' => 'integer',
        'is_quick_dispense' => 'boolean',
        'price' => 'decimal:2',
        'expiry_date' => 'date',
        'wac' => 'decimal:4',
        'last_moved_at' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(StockCategory::class, 'category_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(StockItemPrice::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(StockItemAttributeValue::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_stock_item')
            ->withTimestamps();
    }

    /** الرصيد المحاسبي = رصيد أول المدة + الإضافة − الخصم. */
    public function catalogBalance(): int
    {
        return (int) ($this->opening_qty ?? 0)
            + (int) ($this->addition ?? 0)
            - (int) ($this->discount ?? 0);
    }

    public function availableQty(): int
    {
        return $this->qty - $this->reserved;
    }

    /** كمية العجز المطلوب توريدها (حجز يتجاوز الرصيد الفعلي). */
    public function backorderQty(): int
    {
        return max(0, $this->reserved - $this->qty);
    }

    public function isBackorder(): bool
    {
        return $this->backorderQty() > 0;
    }

    /** حد إعادة التوريد — إن لم يُحدَّد للصنف يُستخدم الافتراضي العام. */
    public function reorderThreshold(): int
    {
        $min = (int) ($this->min_qty ?? 0);

        return $min > 0 ? $min : self::LOW_QTY_THRESHOLD;
    }

    public function isBelowReorderPoint(?int $availableQty = null): bool
    {
        $available = $availableQty ?? $this->availableQty();

        return $available <= $this->reorderThreshold();
    }

    public function recalculateAndSaveStatus(): void
    {
        $status = $this->isBelowReorderPoint()
            ? self::STATUS_LOW
            : self::STATUS_OK;

        if ($this->status !== $status) {
            $this->update(['status' => $status]);
        }
    }

    /** كود الصنف التشغيلي (عمود الأكواد) — يُستخدم في BOM والمسح والصرف. */
    public function operationalCode(): ?string
    {
        $code = trim((string) ($this->alt_codes ?? ''));

        return $code !== '' ? $code : null;
    }

    /** كود الظهور في التوصيف/المعدلات — alt_codes أو رقم الصنف أو الكود الداخلي. */
    public function pickerCode(): string
    {
        $operational = $this->operationalCode();
        if ($operational !== null) {
            return $operational;
        }

        $internal = trim((string) ($this->code ?? ''));
        if ($internal !== '') {
            return $internal;
        }

        return trim((string) ($this->catalog_number ?? ''));
    }

    public function matchesPickerCode(string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        if ($code === $this->pickerCode()) {
            return true;
        }

        return in_array($code, array_filter([
            trim((string) ($this->alt_codes ?? '')),
            trim((string) ($this->code ?? '')),
            trim((string) ($this->catalog_number ?? '')),
        ]), true);
    }

    public static function barcodeForOperationalCode(string $code): string
    {
        return 'BC-'.trim($code);
    }

    public static function findByOperationalCode(string $code, bool $lockForUpdate = false): ?self
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $query = static::query()->where('alt_codes', $code);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $item = $query->first();
        if ($item !== null) {
            return $item;
        }

        // توافق خلفي: بيانات/BOM قديمة تُشير برقم الصنف (code) قبل تعبئة alt_codes.
        $legacyQuery = static::query()->where('code', $code);

        if ($lockForUpdate) {
            $legacyQuery->lockForUpdate();
        }

        $item = $legacyQuery->first();
        if ($item !== null) {
            return $item;
        }

        $catalogQuery = static::query()->where('catalog_number', $code);
        if ($lockForUpdate) {
            $catalogQuery->lockForUpdate();
        }

        return $catalogQuery->first();
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    public static function mapByOperationalCodes(array $codes, string $column): array
    {
        $codes = array_values(array_unique(array_filter(array_map('trim', $codes))));

        if ($codes === []) {
            return [];
        }

        $query = static::query()->where(function ($builder) use ($codes) {
            $builder->whereIn('alt_codes', $codes)
                ->orWhereIn('code', $codes);

            if (Schema::hasColumn('stock_items', 'catalog_number')) {
                $builder->orWhereIn('catalog_number', $codes);
            }
        });

        $map = [];
        foreach ($query->get() as $item) {
            $value = $item->{$column};
            foreach ($codes as $requested) {
                if ($item->matchesPickerCode($requested)) {
                    $map[$requested] = $value;
                }
            }
        }

        return $map;
    }

    /**
     * صفوف اختيار الصنف في التوصيف والمعدلات — code = كود الصنف (alt_codes).
     *
     * @return list<array{code: string, catalog_code: string, name: string, spec: ?string, uom: string, qty: int, reserved: int, available_max: int}>
     */
    public static function pickerCatalogRows(): array
    {
        $columns = ['id', 'code', 'name', 'spec', 'qty', 'reserved', 'uom', 'alt_codes'];
        if (Schema::hasColumn('stock_items', 'catalog_number')) {
            $columns[] = 'catalog_number';
        }
        if (Schema::hasColumn('stock_items', 'brand')) {
            $columns[] = 'brand';
        }

        $query = static::query()->orderBy('name');
        $limit = (int) config('catalog.list_limit', 10000);
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query
            ->get($columns)
            ->map(function (self $item) {
                $pickerCode = $item->pickerCode();
                $catalogCode = trim((string) ($item->catalog_number ?? '')) !== ''
                    ? (string) $item->catalog_number
                    : $item->code;

                return [
                    'code' => $pickerCode,
                    'catalog_code' => $catalogCode,
                    'catalog_number' => $item->catalog_number ?? '',
                    'alt_codes' => $item->operationalCode() ?? '',
                    'name' => $item->name,
                    'brand' => trim((string) ($item->brand ?? '')),
                    'spec' => $item->spec,
                    'uom' => $item->uom ?? 'قطعة',
                    'qty' => (int) $item->qty,
                    'reserved' => (int) $item->reserved,
                    'available_max' => $item->availableQty(),
                ];
            })
            ->filter(fn (array $row) => $row['code'] !== '' && $row['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * صفوف محددة بالأكواد — للبنود المعروضة دون تحميل الكتالوج كاملاً.
     *
     * @param  list<string>  $codes
     * @return list<array{code: string, catalog_code: string, name: string, spec: ?string, uom: string, qty: int, reserved: int, available_max: int}>
     */
    public static function pickerRowsForCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            static fn ($code) => trim((string) $code),
            $codes,
        ))));

        if ($codes === []) {
            return [];
        }

        $columns = ['id', 'code', 'name', 'spec', 'qty', 'reserved', 'uom', 'alt_codes'];
        if (Schema::hasColumn('stock_items', 'catalog_number')) {
            $columns[] = 'catalog_number';
        }

        return static::query()
            ->where(function ($builder) use ($codes) {
                $builder->whereIn('alt_codes', $codes)
                    ->orWhereIn('code', $codes);

                if (Schema::hasColumn('stock_items', 'catalog_number')) {
                    $builder->orWhereIn('catalog_number', $codes);
                }
            })
            ->orderBy('name')
            ->get($columns)
            ->map(function (self $item) {
                $pickerCode = $item->pickerCode();

                return [
                    'code' => $pickerCode,
                    'catalog_code' => $item->code,
                    'catalog_number' => $item->catalog_number ?? '',
                    'alt_codes' => $item->operationalCode() ?? '',
                    'name' => $item->name,
                    'spec' => $item->spec,
                    'uom' => $item->uom ?? 'قطعة',
                    'qty' => (int) $item->qty,
                    'reserved' => (int) $item->reserved,
                    'available_max' => $item->availableQty(),
                ];
            })
            ->filter(fn (array $row) => $row['code'] !== '' && $row['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * بحث الأصناف للتوصيف/المعدلات — يطابق الاسم والكود وalt_codes ورقم الصفحة.
     *
     * @return list<array{code: string, catalog_code: string, name: string, spec: ?string, uom: string, qty: int, reserved: int, available_max: int}>
     */
    public static function searchPickerRows(string $q, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $like = '%'.$q.'%';
        $columns = ['id', 'code', 'name', 'spec', 'qty', 'reserved', 'uom', 'alt_codes'];
        if (Schema::hasColumn('stock_items', 'catalog_number')) {
            $columns[] = 'catalog_number';
        }

        return static::query()
            ->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('alt_codes', 'like', $like)
                    ->orWhere('page_number', 'like', $like);

                if (Schema::hasColumn('stock_items', 'catalog_number')) {
                    $builder->orWhere('catalog_number', 'like', $like);
                }
            })
            ->orderBy('name')
            ->limit(max(10, min(60, $limit)))
            ->get($columns)
            ->map(function (self $item) {
                $pickerCode = $item->pickerCode();

                return [
                    'code' => $pickerCode,
                    'catalog_code' => $item->code,
                    'catalog_number' => $item->catalog_number ?? '',
                    'alt_codes' => $item->operationalCode() ?? '',
                    'name' => $item->name,
                    'spec' => $item->spec,
                    'uom' => $item->uom ?? 'قطعة',
                    'qty' => (int) $item->qty,
                    'reserved' => (int) $item->reserved,
                    'available_max' => $item->availableQty(),
                ];
            })
            ->filter(fn (array $row) => $row['code'] !== '' && $row['name'] !== '')
            ->values()
            ->all();
    }
}
