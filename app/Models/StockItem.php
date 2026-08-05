<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

        return $legacyQuery->first();
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

        return static::query()
            ->whereIn('alt_codes', $codes)
            ->pluck($column, 'alt_codes')
            ->all();
    }

    /**
     * صفوف اختيار الصنف في التوصيف والمعدلات — code = كود الصنف (alt_codes).
     *
     * @return list<array{code: string, catalog_code: string, name: string, spec: ?string, uom: string, qty: int, reserved: int, available_max: int}>
     */
    public static function pickerCatalogRows(): array
    {
        return static::query()
            ->orderBy('alt_codes')
            ->get(['id', 'code', 'name', 'spec', 'qty', 'reserved', 'uom', 'alt_codes'])
            ->map(function (self $item) {
                $operational = $item->operationalCode() ?? '';

                return [
                    'code' => $operational,
                    'catalog_code' => $item->code,
                    'alt_codes' => $operational,
                    'name' => $item->name,
                    'spec' => $item->spec,
                    'uom' => $item->uom,
                    'qty' => (int) $item->qty,
                    'reserved' => (int) $item->reserved,
                    'available_max' => $item->availableQty(),
                ];
            })
            ->filter(fn (array $row) => $row['code'] !== '')
            ->values()
            ->all();
    }
}
