<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function availableQty(): int
    {
        return max(0, $this->qty - $this->reserved);
    }
}
