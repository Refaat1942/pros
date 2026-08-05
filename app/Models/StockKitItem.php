<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockKitItem extends Model
{
    protected $fillable = [
        'stock_kit_id',
        'stock_item_id',
        'qty',
        'sort_order',
    ];

    protected $casts = [
        'qty' => 'integer',
        'sort_order' => 'integer',
    ];

    public function kit(): BelongsTo
    {
        return $this->belongsTo(StockKit::class, 'stock_kit_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
