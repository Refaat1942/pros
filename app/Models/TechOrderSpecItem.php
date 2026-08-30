<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بند التوصيف الفني
 */
class TechOrderSpecItem extends Model
{
    protected $fillable = [
        'tech_order_spec_id',
        'stock_item_code',
        'name',
        'qty',
        'group_label',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
    ];

    public function techOrderSpec(): BelongsTo
    {
        return $this->belongsTo(TechOrderSpec::class);
    }
}
