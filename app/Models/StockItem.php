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
    public const STATUS_OK  = 'ok';
    public const STATUS_LOW = 'low';

    public const LOW_QTY_THRESHOLD = 3;

    protected $fillable = [
        'code',
        'name',
        'spec',
        'category_id',
        'store_class',
        'uom',
        'barcode',
        'qty',
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
        'qty'           => 'integer',
        'reserved'      => 'integer',
        'min_qty'       => 'integer',
        'price'         => 'decimal:2',
        'expiry_date'   => 'date',
        'wac'           => 'decimal:4',
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
}
