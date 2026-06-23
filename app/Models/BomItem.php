<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بند BOM
 */
class BomItem extends Model
{
    public const SOURCE_SPEC = 'spec';
    public const SOURCE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'bom_id',
        'stock_item_code',
        'name',
        'source',
        'qty',
        'unit_cost',
        'issued_qty',
        'returned_qty',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_cost' => 'decimal:2',
        'issued_qty' => 'integer',
        'returned_qty' => 'integer',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_code', 'code');
    }

    public function returnableQty(): int
    {
        return max(0, $this->issued_qty - $this->returned_qty);
    }
}
