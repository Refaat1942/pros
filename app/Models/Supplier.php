<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * المورد — بيانات التعاقد + ربط الأصناف + مديونية المشتريات.
 */
class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'fax',
        'email',
        'address',
        'tax_number',
        'commercial_registry',
        'bank_name',
        'bank_branch',
        'bank_account',
        'iban',
        'notes',
    ];

    public function stockItemPrices(): HasMany
    {
        return $this->hasMany(StockItemPrice::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function debt(): HasOne
    {
        return $this->hasOne(SupplierDebt::class);
    }

    public function stockItems(): BelongsToMany
    {
        return $this->belongsToMany(StockItem::class, 'supplier_stock_item')
            ->withTimestamps();
    }

    public function debtRemaining(): float
    {
        $debt = $this->relationLoaded('debt') ? $this->debt : $this->debt()->first();

        return $debt instanceof SupplierDebt ? $debt->remaining() : 0.0;
    }

    public function debtItemsCount(): int
    {
        if ($this->debtRemaining() <= 0) {
            return 0;
        }

        return (int) $this->stockMovements()
            ->where('movement_type', StockMovement::TYPE_RECEIVE)
            ->distinct('stock_item_id')
            ->count('stock_item_id');
    }

    public function hasFinancialActivity(): bool
    {
        return $this->stockMovements()->exists()
            || $this->stockItemPrices()->exists()
            || $this->debtRemaining() > 0;
    }

    public function canHardDelete(): bool
    {
        return ! $this->hasFinancialActivity();
    }
}
