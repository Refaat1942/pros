<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * حركة مخزنية — receive / issue / return
 */
class StockMovement extends Model
{
    public const TYPE_RECEIVE = 'receive';

    public const TYPE_ISSUE = 'issue';

    public const TYPE_RETURN = 'return';

    protected $fillable = [
        'stock_item_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'balance_after',
        'invoice_no',
        'supplier_id',
        'reference_type',
        'reference_id',
        'performed_by_user_id',
        'moved_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'balance_after' => 'integer',
        'moved_at' => 'datetime',
    ];

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
