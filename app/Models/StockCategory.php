<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * فئة الصنف — يديرها الأدمن، تُستخدم في كatalog الأصناف والأسعار.
 */
class StockCategory extends Model
{
    protected $fillable = [
        'name',
    ];

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class, 'category_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(StockCategoryField::class, 'category_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
