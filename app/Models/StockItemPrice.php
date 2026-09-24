<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دفعة سعر شراء — prices[] في stock-catalog.js
 */
class StockItemPrice extends Model
{
    /** طبقة رصيد أول المدة — الرصيد غير المغطّى بدفعات استلام (شيت الأصناف / الكتالوج). */
    public const OPENING_REF_SUFFIX = '-OPEN';

    public const OPENING_LABEL = 'رصيد أول المدة';

    protected $fillable = [
        'stock_item_id',
        'price_ref',
        'label',
        'supplier_id',
        'supplier_type',
        'supplier_item_code',
        'amount',
        'qty',
        'invoice_no',
        'received_at',
        'supply_request_line_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'qty' => 'float',
        'received_at' => 'date',
    ];

    public static function openingRefFor(StockItem $item): string
    {
        return 'PR-'.$item->code.self::OPENING_REF_SUFFIX;
    }

    public function isOpeningLayer(): bool
    {
        return str_ends_with((string) $this->price_ref, self::OPENING_REF_SUFFIX);
    }

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
}
