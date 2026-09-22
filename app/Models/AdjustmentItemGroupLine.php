<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdjustmentItemGroupLine extends Model
{
    protected $fillable = [
        'adjustment_item_group_id',
        'stock_item_code',
        'qty',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AdjustmentItemGroup::class, 'adjustment_item_group_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_code', 'code');
    }
}
