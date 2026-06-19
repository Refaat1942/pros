<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * رتبة عسكرية — يديرها الأدمن، يختار منها الاستقبال عند تسجيل مريض عسكري.
 */
class MilitaryRank extends Model
{
    protected $fillable = [
        'name',
        'rank_code',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
