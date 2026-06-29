<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockItemAttributeValue extends Model
{
    protected $fillable = [
        'stock_item_id',
        'category_field_id',
        'value',
    ];

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(StockCategoryField::class, 'category_field_id');
    }
}
