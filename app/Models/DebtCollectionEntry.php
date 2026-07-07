<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * سجل دفعة تحصيل — مدني أو عسكري (append-only).
 */
class DebtCollectionEntry extends Model
{
    protected $fillable = [
        'payable_type',
        'payable_id',
        'installment_no',
        'amount',
        'running_collected',
        'remaining_after',
        'recorded_by',
        'recorded_by_name',
        'collected_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'running_collected' => 'decimal:2',
        'remaining_after' => 'decimal:2',
        'collected_at' => 'datetime',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
