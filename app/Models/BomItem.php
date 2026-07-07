<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بند BOM
 */
class BomItem extends Model
{
    public const SOURCE_SPEC = 'spec';

    public const SOURCE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'bom_id',
        'stock_item_code',
        'name',
        'source',
        'qty',
        'unit_cost',
        'issued_qty',
        'returned_qty',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_cost' => 'decimal:2',
        'issued_qty' => 'integer',
        'returned_qty' => 'integer',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_code', 'code');
    }

    public function returnableQty(): int
    {
        return max(0, $this->issued_qty - $this->returned_qty);
    }

    /** كمية مُطلوبة في إذونات ارتجاع لم يُستلمها المخزن بعد. */
    public function pendingReturnQty(): int
    {
        return (int) ReturnNoteLine::query()
            ->where('stock_item_code', $this->stock_item_code)
            ->whereHas('returnNote', fn ($q) => $q
                ->where('bom_id', $this->bom_id)
                ->whereIn('status', [ReturnNote::STATUS_AUTHORIZED, ReturnNote::STATUS_PARTIAL]))
            ->selectRaw('COALESCE(SUM(qty_requested - qty_returned), 0) as pending')
            ->value('pending');
    }

    /**
     * أقصى كمية يمكن طلب ارتجاعها الآن.
     * - بند بكمية واحدة: يُسمح بارتجاعها بالكامل.
     * - بند بكمية أكبر: يُبقى وحدة واحدة على الأقل في الورشة.
     */
    public function returnRequestMaxQty(?int $pendingReturnQty = null): int
    {
        $pending = $pendingReturnQty ?? $this->pendingReturnQty();
        $net = max(0, $this->returnableQty() - $pending);

        if ($net <= 0) {
            return 0;
        }

        if ($this->issued_qty <= 1) {
            return $net;
        }

        return max(0, $net - 1);
    }

    /** @param  iterable<int, Bom>  $boms */
    public static function pendingReturnQtyMapForBoms(iterable $boms): array
    {
        $bomIds = collect($boms)->pluck('id')->filter()->unique()->values();

        if ($bomIds->isEmpty()) {
            return [];
        }

        return ReturnNoteLine::query()
            ->join('return_notes', 'return_notes.id', '=', 'return_note_lines.return_note_id')
            ->whereIn('return_notes.bom_id', $bomIds)
            ->whereIn('return_notes.status', [ReturnNote::STATUS_AUTHORIZED, ReturnNote::STATUS_PARTIAL])
            ->groupBy('return_notes.bom_id', 'return_note_lines.stock_item_code')
            ->selectRaw(
                'return_notes.bom_id as bom_id, return_note_lines.stock_item_code as stock_item_code, '
                .'SUM(return_note_lines.qty_requested - return_note_lines.qty_returned) as pending'
            )
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->bom_id}.{$row->stock_item_code}" => (int) $row->pending])
            ->all();
    }
}
