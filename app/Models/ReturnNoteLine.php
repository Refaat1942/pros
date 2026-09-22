<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بند إذن الارتجاع
 */
class ReturnNoteLine extends Model
{
    protected $fillable = [
        'return_note_id',
        'stock_item_code',
        'name',
        'qty_requested',
        'qty_returned',
        'reason',
    ];

    protected $casts = [
        'qty_requested' => 'decimal:4',
        'qty_returned' => 'decimal:4',
    ];

    public function returnNote(): BelongsTo
    {
        return $this->belongsTo(ReturnNote::class);
    }
}
