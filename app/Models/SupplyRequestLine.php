<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyRequestLine extends Model
{
    public const TYPE_CATALOG = 'catalog';

    public const TYPE_NON_CATALOG = 'non_catalog';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'supply_request_id',
        'line_type',
        'stock_item_id',
        'description',
        'qty',
        'uom',
        'spec',
        'notes',
        'status',
        'resolved_stock_item_id',
        'stock_movement_id',
        'received_at',
    ];

    protected $casts = [
        'qty' => 'integer',
        'received_at' => 'datetime',
    ];

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function resolvedStockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'resolved_stock_item_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function isNonCatalog(): bool
    {
        return $this->line_type === self::TYPE_NON_CATALOG;
    }

    public function isCatalog(): bool
    {
        return $this->line_type === self::TYPE_CATALOG;
    }

    /** الصنف المخزني الجاهز للاستلام (كتالوج أو بعد الربط). */
    public function receivableStockItemId(): ?int
    {
        if ($this->isCatalog()) {
            return $this->stock_item_id;
        }

        return $this->resolved_stock_item_id;
    }

    public function displayLabel(): string
    {
        if ($this->isNonCatalog()) {
            return (string) $this->description;
        }

        $item = $this->relationLoaded('stockItem') ? $this->stockItem : $this->stockItem()->first();

        return $item
            ? trim(($item->code ?? '').' — '.($item->name ?? ''))
            : (string) $this->description;
    }
}
