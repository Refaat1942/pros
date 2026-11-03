<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** تبديل صنف مع المورد — يتنفّذ (خصم + إضافة) عند اعتماد أدمن المخازن فقط. */
class StockSwapRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'swap_no', 'status', 'supplier_id', 'out_stock_item_id', 'out_qty', 'in_stock_item_id', 'new_item', 'in_qty',
        'price_difference', 'notes', 'requested_by_user_id', 'approved_by_user_id', 'approved_at', 'rejection_reason',
        'out_value', 'out_movement_id', 'in_movement_id',
    ];

    protected $casts = [
        'new_item' => 'array',
        'out_qty' => 'decimal:4',
        'in_qty' => 'decimal:4',
        'price_difference' => 'decimal:2',
        'out_value' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function outItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'out_stock_item_id');
    }

    public function inItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'in_stock_item_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function inLabel(): string
    {
        if ($this->inItem) {
            return trim($this->inItem->code.' — '.$this->inItem->name);
        }

        return trim((string) ($this->new_item['name'] ?? '—')).' (صنف جديد)';
    }
}
