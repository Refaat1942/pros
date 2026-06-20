<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * المورد — طبقة الموردين (بدون فواتير مشتريات أو أوامر شراء)
 */
class Supplier extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
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
}
