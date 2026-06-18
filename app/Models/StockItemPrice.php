<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دفعة سعر شراء — prices[] في stock-catalog.js
 */
class StockItemPrice extends Model
{
    protected $fillable = [
        'stock_item_id',
        'price_ref',
        'label',
        'supplier_id',
        'supplier_type',
        'supplier_item_code',
        'amount',
        'qty',
        'invoice_no',
        'received_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'qty' => 'integer',
        'received_at' => 'date',
    ];

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
