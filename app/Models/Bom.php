<?php

namespace App\Models;

use App\Enums\StockWarehouseType;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * قائمة مواد التشغيل — clinic_bom_inventory
 */


class Bom extends Model /// Bill of Materials  || "الروشتة التصنيعية للمريض" أو "قائمة المقادير".
{
    public const STAGE_RAW = 'raw';
    public const STAGE_WIP = 'wip';
    public const STAGE_FINISHED = 'finished';

    protected $fillable = [
        'bom_no',
        'case_id',
        'order_ref',
        'quote_no',
        'patient_name',
        'stage',
        'stock_reserved_at',
        'released_at',
        'finished_at',
    ];

    protected $casts = [
        'stock_reserved_at' => 'datetime',
        'released_at'       => 'datetime',
        'finished_at'       => 'datetime',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    public function returnNotes(): HasMany
    {
        return $this->hasMany(ReturnNote::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function totalValue(): float
    {
        return $this->items->sum(fn (BomItem $item) => (float) $item->unit_cost * $item->qty);
    }

    public function warehouseType(): ?StockWarehouseType
    {
        return StockWarehouseType::fromBomStage($this->stage);
    }

    public function warehouseLabel(): string
    {
        return StockWarehouseType::labelForBomStage($this->stage);
    }
}
