<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** طلب استلام وارد بانتظار اعتماد أدمن المخازن — الرصيد يتضاف عند الاعتماد فقط. */
class StockReceiptRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'status', 'stock_item_id', 'supplier_id', 'supply_request_line_id', 'qty', 'unit_price', 'quantity_basis',
        'invoice_no', 'moved_at', 'document_path', 'document_original_name', 'document_mime',
        'requested_by_user_id', 'approved_by_user_id', 'approved_at', 'rejection_reason', 'stock_movement_id',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'moved_at' => 'date',
        'approved_at' => 'datetime',
    ];

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplyRequestLine(): BelongsTo
    {
        return $this->belongsTo(SupplyRequestLine::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
