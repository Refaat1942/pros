<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بند توصية مرتبط بالحالة
 */
class CaseRecommendation extends Model
{
    protected $fillable = [
        'case_id',
        'stock_item_code',
        'name',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_code', 'code');
    }
}
