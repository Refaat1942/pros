<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بند طلب التسعير
 */
class PricingRequestItem extends Model
{
    protected $fillable = [
        'pricing_request_id',
        'stock_item_code',
        'name',
        'source',
        'qty',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function pricingRequest(): BelongsTo
    {
        return $this->belongsTo(PricingRequest::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_code', 'code');
    }
}
