<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نمط تكاليف — طرف صناعي (مكوّنات + ربح) أو صرف سريع (ربح فقط).
 */
class CostingMode extends Model
{
    protected $fillable = [
        'key',
        'label',
        'profit_rate',
        'has_components',
        'active',
        'sort',
    ];

    protected $casts = [
        'profit_rate' => 'decimal:2',
        'has_components' => 'boolean',
        'active' => 'boolean',
        'sort' => 'integer',
    ];

    public function components(): HasMany
    {
        return $this->hasMany(CostingComponent::class)->orderBy('sort');
    }
}
