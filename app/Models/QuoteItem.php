<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بند عرض السعر
 */
class QuoteItem extends Model
{
    protected $fillable = [
        'quote_id',
        'name',
        'stock_item_code',
        'qty',
        'amount',
    ];

    protected $casts = [
        'qty' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
