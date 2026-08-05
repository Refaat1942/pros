<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * طقم جاهز أو مجموعة مخصصات — تُفكّك إلى مكوّنات عند اختيارها في التوصيف/المعدلات.
 */
class StockKit extends Model
{
    public const TYPE_ASSEMBLY = 'assembly';

    public const TYPE_ACCESSORY = 'accessory';

    protected $fillable = [
        'code',
        'name',
        'type',
        'spec_group',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockKitItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isAccessoryKit(): bool
    {
        return $this->type === self::TYPE_ACCESSORY;
    }
}
