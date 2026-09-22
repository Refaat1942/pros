<?php

namespace App\Models;

use App\Support\StockQuantity;
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
        'group_label',
        'unit_cost',
        'issued_qty',
        'returned_qty',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'issued_qty' => 'decimal:4',
        'returned_qty' => 'decimal:4',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_code', 'code');
    }

    public function returnableQty(): float
    {
        return max(0.0, (float) $this->issued_qty - (float) $this->returned_qty);
    }

    /** كمية مُطلوبة في إذونات ارتجاع لم يُستلمها المخزن بعد. */
    public function pendingReturnQty(): float
    {
        return (float) ReturnNoteLine::query()
            ->where('stock_item_code', $this->stock_item_code)
            ->whereHas('returnNote', fn ($q) => $q
                ->where('bom_id', $this->bom_id)
                ->whereIn('status', [ReturnNote::STATUS_AUTHORIZED, ReturnNote::STATUS_PARTIAL]))
            ->selectRaw('COALESCE(SUM(qty_requested - qty_returned), 0) as pending')
            ->value('pending');
    }

    /**
     * أقصى كمية يمكن طلب ارتجاعها الآن.
     * - بعد التسليم (BOM تام): يُسمح بارتجاع كل الكمية المُصرفة المتبقية.
     * - أثناء التشغيل: بند بكمية واحدة يُرتجع بالكامل؛ بند بكمية أكبر يُبقى وحدة في قسم الإنتاج.
     */
    public function returnRequestMaxQty(?float $pendingReturnQty = null, ?string $bomStage = null): float
    {
        $pending = $pendingReturnQty ?? $this->pendingReturnQty();
        $net = max(0.0, $this->returnableQty() - $pending);

        if ($net <= 0) {
            return 0.0;
        }

        if ($bomStage === Bom::STAGE_FINISHED) {
            return round($net, 4);
        }

        $stockItem = StockItem::findByOperationalCode($this->stock_item_code, true);
        $uom = $stockItem?->uom ?? 'قطعة';
        if (StockQuantity::isFractionalUom($uom)) {
            return round($net, 4);
        }

        if ((float) $this->issued_qty <= 1) {
            return round($net, 4);
        }

        return round(max(0.0, $net - 1), 4);
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
            ->mapWithKeys(fn ($row) => ["{$row->bom_id}.{$row->stock_item_code}" => (float) $row->pending])
            ->all();
    }
}
